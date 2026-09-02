<?php
/**
 * GitHub Auto-Updater
 *
 * Enables automatic updates from GitHub releases for WordPress plugins.
 *
 * @package French_Schools_Map
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FSM_GitHub_Updater
 *
 * Handles automatic updates from GitHub releases.
 */
class FSM_GitHub_Updater
{

    // =========================================================================
    // CONFIGURATION - CUSTOMIZE THESE VALUES FOR YOUR PLUGIN
    // =========================================================================

    /**
     * GitHub username or organization.
     *
     * @var string
     */
    private const GITHUB_USER = 'guilamu';

    /**
     * GitHub repository name.
     *
     * @var string
     */
    private const GITHUB_REPO = 'french-schools-map';

    /**
     * Plugin file path relative to plugins directory.
     * Format: 'folder-name/main-file.php'
     *
     * @var string
     */
    private const PLUGIN_FILE = 'french-schools-map/french-schools-map.php';

    /**
     * Plugin slug (used for plugin info popup).
     *
     * @var string
     */
    private const PLUGIN_SLUG = 'french-schools-map';

    /**
     * Plugin display name.
     *
     * @var string
     */
    private const PLUGIN_NAME = 'French Schools Map';

    /**
     * Plugin description.
     *
     * @var string
     */
    private const PLUGIN_DESCRIPTION = 'Carte interactive des établissements scolaires français basée sur OpenStreetMap et les données open data du Ministère de l\'Éducation Nationale.';

    /**
     * Minimum WordPress version required.
     *
     * @var string
     */
    private const REQUIRES_WP = '5.8';

    /**
     * WordPress version tested up to.
     *
     * Reported to WordPress and read by compatibility monitors, so it must be
     * a deliberate statement of what was actually exercised — bump it as part
     * of releasing, not automatically from the running site's version.
     *
     * @var string
     */
    private const TESTED_WP = '7.1';

    /**
     * Minimum PHP version required.
     *
     * @var string
     */
    private const REQUIRES_PHP = '7.4';

    /**
     * Text domain for translations.
     *
     * @var string
     */
    private const TEXT_DOMAIN = 'french-schools-map';

    // =========================================================================
    // CACHE SETTINGS (usually no need to change)
    // =========================================================================

    /**
     * Cache key prefix for GitHub release data.
     *
     * @var string
     */
    private const CACHE_KEY = 'fsm_github_release';

    /**
     * Cache key for the release list used to build the changelog.
     *
     * @var string
     */
    private const CACHE_KEY_RELEASES = 'fsm_github_releases';

    /**
     * How many releases to pull when listing them (GitHub caps this at 100).
     *
     * @var int
     */
    private const RELEASES_PER_PAGE = 30;

    /**
     * Cache expiration in seconds (12 hours default).
     *
     * @var int
     */
    private const CACHE_EXPIRATION = 43200;

    /**
     * Optional GitHub token for private repos or to avoid rate limits (leave empty for public repos).
     * Use a Classic PAT with `repo` scope or a fine-grained token with read access to the repo.
     *
     * @var string
     */
    private const GITHUB_TOKEN = '';

    // =========================================================================
    // IMPLEMENTATION (no changes needed below this line)
    // =========================================================================

    /**
     * Initialize the updater.
     *
     * @return void
     */
    public static function init(): void
    {
        add_filter('update_plugins_github.com', array(self::class, 'check_for_update'), 10, 4);
        add_filter('plugins_api', array(self::class, 'plugin_info'), 20, 3);
        add_filter('plugins_api_result', array(self::class, 'finalize_plugin_info'), PHP_INT_MAX, 3);
        add_filter('upgrader_source_selection', array(self::class, 'fix_folder_name'), 10, 4);
        add_action('admin_head', array(self::class, 'plugin_info_css'));
    }

    /**
     * If you later add scheduled cleanup or background refresh logic via
     * the cron_schedules filter, do not wrap the schedule display label in
     * translation functions here unless you guard for init already having run.
     *
     * In WordPress 6.7+, translation calls inside cron_schedules can trigger
     * "Translation loading triggered too early" warnings because other plugins
     * may call wp_get_schedules() before init. Prefer a plain static string for
     * admin-only schedule labels, e.g. 'Once Monthly'.
     */

    /**
     * Call a GitHub repository endpoint and decode the JSON response.
     *
     * @param string $endpoint Path below /repos/{owner}/{repo}/, e.g. "releases/latest".
     * @return array|null Decoded payload, or null on any failure.
     */
    private static function request_github(string $endpoint): ?array
    {
        $response = wp_remote_get(
            sprintf('https://api.github.com/repos/%s/%s/%s', self::GITHUB_USER, self::GITHUB_REPO, $endpoint),
            array(
                'user-agent' => 'WordPress/' . self::PLUGIN_SLUG,
                'timeout' => 15,
                'headers' => !empty(self::GITHUB_TOKEN)
                    ? array('Authorization' => 'token ' . self::GITHUB_TOKEN)
                    : array(),
            )
        );

        // Handle request errors
        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(self::PLUGIN_NAME . ' Update Error: ' . $response->get_error_message());
            }
            return null;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if (200 !== $response_code) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(self::PLUGIN_NAME . " Update Error: HTTP {$response_code}");
            }
            return null;
        }

        // Parse JSON response
        $data = json_decode(wp_remote_retrieve_body($response), true);

        return is_array($data) ? $data : null;
    }

    /**
     * Get the latest release from GitHub with caching.
     *
     * @return array|null Release data or null on failure.
     */
    private static function get_release_data(): ?array
    {
        $release_data = get_transient(self::CACHE_KEY);

        if (false !== $release_data && is_array($release_data)) {
            return $release_data;
        }

        $release_data = self::request_github('releases/latest');

        if (empty($release_data['tag_name'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(self::PLUGIN_NAME . ' Update Error: No tag_name in release');
            }
            return null;
        }

        // Cache the release data
        set_transient(self::CACHE_KEY, $release_data, self::CACHE_EXPIRATION);

        return $release_data;
    }

    /**
     * Get every published release newer than the installed version, newest first.
     *
     * The local README.md only documents versions up to the installed one, so a
     * site several releases behind would otherwise show a gap: the latest
     * release's notes, then nothing until the installed version. Listing the
     * releases closes that gap.
     *
     * Only the three fields the changelog needs are cached; a release payload
     * carries assets, uploader accounts and reactions that would bloat the
     * transient for no benefit.
     *
     * @param string $installed_version Currently installed plugin version.
     * @return array<int, array{tag_name: string, body: string, published_at: string}>
     */
    private static function get_newer_releases(string $installed_version): array
    {
        $releases = get_transient(self::CACHE_KEY_RELEASES);

        if (false === $releases || !is_array($releases)) {
            $payload = self::request_github('releases?per_page=' . self::RELEASES_PER_PAGE);

            if (null === $payload) {
                return array();
            }

            $releases = array();

            foreach ($payload as $release) {
                // Drafts and pre-releases are excluded, matching what
                // /releases/latest reports as the installable version.
                if (!is_array($release)
                    || empty($release['tag_name'])
                    || !empty($release['draft'])
                    || !empty($release['prerelease'])
                ) {
                    continue;
                }

                $releases[] = array(
                    'tag_name'     => $release['tag_name'],
                    'body'         => $release['body'] ?? '',
                    'published_at' => $release['published_at'] ?? '',
                );
            }

            set_transient(self::CACHE_KEY_RELEASES, $releases, self::CACHE_EXPIRATION);
        }

        $newer = array();

        foreach ($releases as $release) {
            if (version_compare($installed_version, ltrim($release['tag_name'], 'v'), '<')) {
                $newer[] = $release;
            }
        }

        // GitHub orders by creation date; order by version instead so a
        // back-ported tag cannot land above a higher version.
        usort(
            $newer,
            static function ($a, $b) {
                return version_compare(ltrim($b['tag_name'], 'v'), ltrim($a['tag_name'], 'v'));
            }
        );

        return $newer;
    }

    /**
     * Get the download URL for the plugin package.
     *
     * Prefers custom release assets (e.g., french-schools-map.zip) over
     * GitHub's auto-generated zipball for cleaner folder naming.
     *
     * @param array $release_data Release data from GitHub API.
     * @return string Download URL for the plugin package.
     */
    private static function get_package_url(array $release_data): string
    {
        // Look for a custom .zip asset (preferred)
        if (!empty($release_data['assets']) && is_array($release_data['assets'])) {
            foreach ($release_data['assets'] as $asset) {
                if (
                    isset($asset['browser_download_url']) &&
                    isset($asset['name']) &&
                    str_ends_with($asset['name'], '.zip')
                ) {
                    return $asset['browser_download_url'];
                }
            }
        }

        // Fallback to GitHub's auto-generated zipball
        return $release_data['zipball_url'] ?? '';
    }

    /**
     * Rebuild the final plugin information object after all earlier filters.
     *
     * A third-party plugin's `plugins_api` filter that incorrectly returns
     * `false` for slugs it does not own (instead of passing `$res` through)
     * silently discards the object we built - core then falls back to
     * wordpress.org, which does not know this slug and renders
     * "Plugin not found.". Rebuilding a fresh object on `plugins_api_result`
     * at the highest practical priority guarantees the modal always renders,
     * even when an earlier filter corrupted or replaced our result.
     *
     * @param false|object|array $result Plugin API result.
     * @param string             $action Requested action.
     * @param object             $args   API arguments.
     * @return false|object|array
     */
    public static function finalize_plugin_info($result, $action, $args)
    {
        if ('plugin_information' !== $action) {
            return $result;
        }

        if (!isset($args->slug) || self::PLUGIN_SLUG !== $args->slug) {
            return $result;
        }

        return self::plugin_info(false, $action, $args);
    }

    /**
     * Get a package URL suitable for the plugin details footer action button.
     *
     * WordPress only renders the plugin-information footer button when the
     * plugin info payload includes a non-empty download_link, even if the
     * plugin is already installed and active. When the GitHub API is
     * unreachable or rate-limited, fall back to the "latest release" asset URL.
     *
     * @param array|null $release_data Release data from GitHub API.
     * @return string
     */
    private static function get_plugin_info_download_link(?array $release_data = null): string
    {
        if (is_array($release_data)) {
            $package_url = self::get_package_url($release_data);

            if ('' !== $package_url) {
                return $package_url;
            }
        }

        return sprintf(
            'https://github.com/%s/%s/releases/latest/download/%s.zip',
            self::GITHUB_USER,
            self::GITHUB_REPO,
            self::GITHUB_REPO
        );
    }

    /**
     * Check for plugin updates from GitHub.
     *
     * @param array|false $update      The plugin update data.
     * @param array       $plugin_data Plugin headers.
     * @param string      $plugin_file Plugin file path.
     * @param array       $locales     Installed locales.
     * @return array|false Updated plugin data or false.
     */
    public static function check_for_update($update, array $plugin_data, string $plugin_file, $locales)
    {
        // Verify this is our plugin
        if (self::PLUGIN_FILE !== $plugin_file) {
            return $update;
        }

        $release_data = self::get_release_data();
        if (null === $release_data) {
            return $update;
        }

        // Clean version (remove 'v' prefix: v1.0.0 -> 1.0.0)
        $new_version = ltrim($release_data['tag_name'], 'v');

        // Compare versions - only return update if newer version exists
        if (version_compare($plugin_data['Version'], $new_version, '>=')) {
            return $update;
        }

        // Build update object.
        //
        // The keys id, slug, plugin, and new_version are required for reliable
        // compatibility with WordPress core update screens. Omitting them can
        // trigger notices such as "Undefined property: stdClass::$slug" on
        // wp-admin/update-core.php in newer WordPress versions.
        return array(
            'id'           => 'github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
            'slug'         => self::PLUGIN_SLUG,
            'plugin'       => self::PLUGIN_FILE,
            'new_version'  => $new_version,
            'version'      => $new_version,
            'package'      => self::get_package_url($release_data),
            'url'          => $release_data['html_url'],
            'tested'       => self::TESTED_WP,
            'requires_php' => self::REQUIRES_PHP,
            'compatibility' => new stdClass(),
            'icons'        => array(),
            'banners'      => array(),
        );
    }

    /**
     * Provide plugin information for the WordPress plugin details popup.
     *
     * Reads sections (description, installation, FAQ, changelog) from the
     * local README.md. When an update is available, the GitHub release body
     * is prepended to the changelog so users see what's new before updating.
     *
     * @param false|object|array $res    The result object or array.
     * @param string             $action The type of information being requested.
     * @param object             $args   Plugin API arguments.
     * @return false|object Plugin information or false.
     */
    public static function plugin_info($res, $action, $args)
    {
        if ('plugin_information' !== $action) {
            return $res;
        }

        if (!isset($args->slug) || self::PLUGIN_SLUG !== $args->slug) {
            return $res;
        }

        $plugin_file = WP_PLUGIN_DIR . '/' . self::PLUGIN_FILE;
        $plugin_data = get_plugin_data($plugin_file, false, false);
        $release_data = self::get_release_data();

        $version = $release_data
            ? ltrim($release_data['tag_name'], 'v')
            : ($plugin_data['Version'] ?? '1.0.0');

        // IMPORTANT: Always return a valid stdClass to prevent WordPress from
        // falling back to WordPress.org API (which fails with "Plugin not found"
        // for custom/GitHub-hosted plugins).
        $res               = new stdClass();
        $res->name         = self::PLUGIN_NAME;
        $res->slug         = self::PLUGIN_SLUG;
        $res->plugin       = self::PLUGIN_FILE; // CRITICAL for install status detection
        // CRITICAL: marks the plugin as not hosted on WordPress.org, otherwise
        // install_plugin_information() prints a "WordPress.org Plugin Page" link
        // built from the slug — a 404 for a GitHub-only plugin.
        $res->external     = true;
        $res->version      = $version;
        $res->author       = sprintf('<a href="https://github.com/%s">%s</a>', self::GITHUB_USER, self::GITHUB_USER);
        $res->homepage     = sprintf('https://github.com/%s/%s', self::GITHUB_USER, self::GITHUB_REPO);
        $res->requires     = self::REQUIRES_WP;
        $res->tested       = self::TESTED_WP;
        $res->requires_php = self::REQUIRES_PHP;

        // Always non-empty: WordPress only renders the modal footer action
        // button (Active / Activate / Update) when download_link is set.
        $res->download_link = self::get_plugin_info_download_link($release_data);

        if ($release_data) {
            $res->last_updated = $release_data['published_at'] ?? '';
        }

        // Build sections from local README.md.
        // Only add tabs whose content is non-empty — WordPress displays
        // a tab for every key present in the sections array, so omitting
        // a key hides the tab entirely.
        $readme = self::parse_readme();

        $res->sections = array(
            'description'  => !empty($readme['description'])
                ? $readme['description']
                : '<p>' . esc_html(self::PLUGIN_DESCRIPTION) . '</p>',
        );

        if (!empty($readme['installation'])) {
            $res->sections['installation'] = $readme['installation'];
        }

        if (!empty($readme['faq'])) {
            $res->sections['faq'] = $readme['faq'];
        }

        // The local README only documents the installed version and older, so
        // every newer release is prepended from its GitHub release notes.
        $changelog_html      = '';
        $installed_version   = $plugin_data['Version'] ?? '0.0.0';

        foreach (self::get_newer_releases($installed_version) as $pending) {
            $pending_version = ltrim($pending['tag_name'], 'v');

            // A release with no notes has nothing to show, and one the README
            // already documents would be listed twice.
            if ('' === trim($pending['body'])
                || self::changelog_has_version($readme['changelog'] ?? '', $pending_version)
            ) {
                continue;
            }

            $changelog_html .= self::render_pending_release($pending, $pending_version);
        }

        if (!empty($readme['changelog'])) {
            $changelog_html .= self::mark_installed_version($readme['changelog'], $installed_version);
        }

        $res->sections['changelog'] = !empty($changelog_html)
            ? $changelog_html
            : sprintf(
                '<p>See <a href="https://github.com/%s/%s/releases" target="_blank">GitHub releases</a> for changelog.</p>',
                esc_attr(self::GITHUB_USER),
                esc_attr(self::GITHUB_REPO)
            );

        return $res;
    }

    /**
     * Tell whether the README changelog already documents a given version.
     *
     * Guards against printing the release twice when the changelog entry was
     * committed to README.md before the matching GitHub release was tagged.
     *
     * @param string $changelog_html Changelog HTML built from README.md.
     * @param string $version        Version to look for.
     * @return bool
     */
    private static function changelog_has_version(string $changelog_html, string $version): bool
    {
        if ('' === $changelog_html || '' === $version) {
            return false;
        }

        return 1 === preg_match(
            '#<h[23]>\s*v?' . preg_quote($version, '#') . '\b#i',
            $changelog_html
        );
    }

    /**
     * Render the changelog entry for a release that is not installed yet.
     *
     * The entry comes from the GitHub release body, whereas every other entry
     * comes from the local README.md. Without normalisation the two sources do
     * not match: the README uses "### x.y.z - YYYY-MM-DD" (rendered as <h3>)
     * while a release body carries whatever heading levels its author typed,
     * plus GitHub's auto-appended "Full Changelog" URL.
     *
     * @param array  $release_data Release payload from the GitHub API.
     * @param string $version      Release version (tag without the leading "v").
     * @return string HTML for the pending release entry.
     */
    private static function render_pending_release(array $release_data, string $version): string
    {
        $date = !empty($release_data['published_at'])
            ? mysql2date('Y-m-d', $release_data['published_at'], false)
            : '';

        $title = '' !== $date ? $version . ' - ' . $date : $version;

        // <div class> and <span class> survive the wp_kses() pass applied by
        // install_plugin_information(); the styling comes from plugin_info_css().
        return '<div class="fsm-release-pending">'
            . '<h3>' . esc_html($title)
            . ' <span class="fsm-release-badge">'
            . esc_html__('Not installed yet', self::TEXT_DOMAIN)
            . '</span></h3>'
            . self::normalize_release_body($release_data['body'], $version)
            . '</div>';
    }

    /**
     * Clean up a GitHub release body so it renders like a README changelog entry.
     *
     * - drops a leading heading that only repeats the version, which is already
     *   printed as the entry title;
     * - rewrites GitHub's "Full Changelog: <url>" footer into a short link
     *   instead of a full-width raw URL;
     * - flattens every heading to <h4>, so that a release body written with
     *   "##" does not render a heading larger than the entry title above it.
     *
     * @param string $body    Raw Markdown release body.
     * @param string $version Release version, used to detect the duplicate title.
     * @return string Sanitised HTML.
     */
    private static function normalize_release_body(string $body, string $version): string
    {
        $body = trim($body);

        // Leading "# v1.5.1", "## 1.5.1 - 2026-09-01", "### Release 1.5.1"...
        $body = preg_replace(
            '/\A#{1,6}\s*(?:release\s+)?v?' . preg_quote($version, '/') . '\b[^\n]*\n+/i',
            '',
            $body,
            1
        );

        // GitHub footer: **Full Changelog**: https://github.com/o/r/compare/v1.5.0...v1.5.1
        $body = preg_replace_callback(
            '#\*\*Full Changelog\*\*:\s*(\S+/compare/(\S+))#i',
            static function ($m) {
                return '**Full Changelog**: [' . $m[2] . '](' . $m[1] . ')';
            },
            $body
        );

        $html = self::markdown_to_html($body);

        // Flatten headings to <h4> (see the method docblock).
        $html = preg_replace('#<(/?)h[1-6]\b#i', '<$1h4', $html);

        return $html;
    }

    /**
     * Tag the installed version's heading in the README changelog.
     *
     * Gives the reader a reference point for how far behind their install is,
     * which only matters once a newer release is listed above it.
     *
     * @param string $changelog_html Changelog HTML built from README.md.
     * @param string $version        Currently installed version.
     * @return string Changelog HTML with the installed entry tagged.
     */
    private static function mark_installed_version(string $changelog_html, string $version): string
    {
        if ('' === $version) {
            return $changelog_html;
        }

        return preg_replace(
            '#<h3>(\s*v?' . preg_quote($version, '#') . '\b[^<]*)</h3>#i',
            '<h3>$1 <span class="fsm-release-badge fsm-release-badge-installed">'
                . esc_html__('Installed', self::TEXT_DOMAIN)
                . '</span></h3>',
            $changelog_html,
            1
        );
    }

    /**
     * Inject CSS overrides and optional sidebar info in the plugin-information iframe.
     *
     * wp_kses_post() strips <style> tags from section content, so CSS must be
     * injected via the admin_head hook which fires inside the iframe's <head>.
     *
     * A CSS geometric pattern replaces the banner image area: WordPress only adds
     * the `with-banner` class to #plugin-information-title when $api->banners
     * contains real image URLs. A small JS snippet adds the class manually so the
     * CSS pattern and h2 styling apply without any external image.
     */
    public static function plugin_info_css(): void
    {
        if (!isset($_GET['plugin'], $_GET['tab'])) {
            return;
        }
        if ('plugin-information' !== sanitize_text_field(wp_unslash($_GET['tab']))
            || self::PLUGIN_SLUG !== sanitize_text_field(wp_unslash($_GET['plugin']))) {
            return;
        }

        // CSS pattern variables for the banner background.
        $pattern_css = '--s: 27px;'
            . '--c1: #b2b2b2;'
            . '--c2: #ffffff;'
            . '--c3: #d9d9d9;'
            . '--_g: var(--c3) 0 120deg, #0000 0;';

        $pattern_bg = 'conic-gradient(from -60deg at 50% calc(100%/3), var(--_g)),'
            . 'conic-gradient(from 120deg at 50% calc(200%/3), var(--_g)),'
            . 'conic-gradient(from 60deg at calc(200%/3), var(--c3) 60deg, var(--c2) 0 120deg, #0000 0),'
            . 'conic-gradient(from 180deg at calc(100%/3), var(--c1) 60deg, var(--_g)),'
            . 'linear-gradient(90deg, var(--c1) calc(100%/6), var(--c2) 0 50%,'
            . 'var(--c1) 0 calc(500%/6), var(--c2) 0)';

        echo '<style>'
            // CSS geometric pattern banner (replaces banner image).
            . '#plugin-information-title.with-banner {'
            .   $pattern_css
            .   'background: ' . $pattern_bg . ' !important;'
            .   'background-size: calc(1.732 * var(--s)) var(--s) !important;'
            . '}'
            // Plugin name styled like official WordPress banner h2.
            . '#plugin-information-title.with-banner h2 {'
            .   'position: relative;'
            .   'font-family: "Helvetica Neue", sans-serif;'
            .   'display: inline-block;'
            .   'font-size: 30px;'
            .   'line-height: 1.68;'
            .   'box-sizing: border-box;'
            .   'max-width: 100%;'
            .   'padding: 0 15px;'
            .   'margin-top: 174px;'
            .   'color: #fff;'
            .   'background: rgba(29, 35, 39, 0.9);'
            .   'text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);'
            .   'box-shadow: 0 0 30px rgba(255, 255, 255, 0.1);'
            .   'border-radius: 8px;'
            . '}'
            // Section content fixes.
            . '#section-holder .section h2 { margin: 1.5em 0 0.5em; clear: none; }'
            . '#section-holder .section h3 { margin: 1.5em 0 0.5em; }'
            . '#section-holder .section h4 { margin: 1.2em 0 0.4em; font-size: 13px; }'
            . '#section-holder .section > :first-child { margin-top: 0; }'
            // Pending release: set apart from the entries already installed.
            . '.fsm-release-pending {'
            .   'margin: 0 0 1.6em;'
            .   'padding: 2px 16px 10px;'
            .   'border-left: 4px solid #2271b1;'
            .   'background: #f0f6fc;'
            .   'border-radius: 0 3px 3px 0;'
            . '}'
            . '.fsm-release-pending > h3:first-child { margin-top: 0.8em; }'
            . '.fsm-release-badge {'
            .   'display: inline-block;'
            .   'vertical-align: middle;'
            .   'margin-left: 8px;'
            .   'padding: 1px 8px;'
            .   'border-radius: 9px;'
            .   'background: #2271b1;'
            .   'color: #fff;'
            .   'font-size: 11px;'
            .   'font-weight: 600;'
            .   'line-height: 1.7;'
            .   'text-transform: uppercase;'
            .   'letter-spacing: 0.02em;'
            . '}'
            . '.fsm-release-badge-installed { background: #dcdcde; color: #50575e; }'
            . '.md-table { display: table; width: 100%; border-collapse: collapse; margin: 1em 0; font-size: 13px; }'
            . '.md-tr { display: table-row; }'
            . '.md-tr > span { display: table-cell; padding: 6px 10px; border: 1px solid #ddd; vertical-align: top; }'
            . '.md-th > span { font-weight: 600; background: #f5f5f5; }'
            . '</style>';

        // JS: add with-banner class (WordPress only adds it for real banner images).
        echo '<script>'
            . 'document.addEventListener("DOMContentLoaded",function(){'
            // Add with-banner class to trigger the CSS pattern background.
            . 'var title=document.getElementById("plugin-information-title");'
            . 'if(title){title.classList.add("with-banner");}'
            . '});'
            . '</script>';
    }

    // ------------------------------------------------------------------
    // README.md parsing
    // ------------------------------------------------------------------

    /**
     * Parse the local README.md into description, installation, FAQ and changelog HTML.
     *
     * Splits the Markdown by ## headers and categorizes each section:
     * - "Installation", "FAQ", "Changelog" → their own tabs.
     * - Utility sections (Requirements, License, Project Structure,
     *   Acknowledgements) → excluded.
     * - Everything else → appended to the description tab.
     *
     * @return array{description: string, installation: string, faq: string, changelog: string}
     */
    private static function parse_readme(): array
    {
        $readme_path = WP_PLUGIN_DIR . '/' . dirname(self::PLUGIN_FILE) . '/README.md';

        if (!file_exists($readme_path)) {
            return array();
        }

        $content = file_get_contents($readme_path);
        if (false === $content) {
            return array();
        }

        // Remove the main title line (# Title).
        $content = preg_replace('/^#\s+[^\n]+\n*/m', '', $content, 1);

        // Sections that are NOT part of the description tab.
        $utility_sections = array(
            'changelog', 'requirements', 'installation', 'faq',
            'project structure', 'acknowledgements', 'license',
        );

        // Split content by ## headers.
        $parts = preg_split('/^##\s+/m', $content);

        $description  = trim($parts[0] ?? '');
        $installation = '';
        $faq          = '';
        $changelog    = '';

        for ($i = 1, $count = count($parts); $i < $count; $i++) {
            $lines = explode("\n", $parts[$i], 2);
            $title = strtolower(trim($lines[0]));
            $body  = trim($lines[1] ?? '');

            if ('installation' === $title) {
                $installation .= $body . "\n\n";
            } elseif ('faq' === $title) {
                $faq .= $body . "\n\n";
            } elseif ('changelog' === $title) {
                $changelog .= $body . "\n\n";
            } elseif (!in_array($title, $utility_sections, true)) {
                // Include in description (e.g. "Key Features", "Fonctionnalités").
                $description .= "\n\n## " . trim($lines[0]) . "\n" . $body;
            }
        }

        return array(
            'description'  => self::markdown_to_html(trim($description)),
            'installation' => self::markdown_to_html(trim($installation)),
            'faq'          => self::markdown_to_html(trim($faq)),
            'changelog'    => self::markdown_to_html(trim($changelog)),
        );
    }

    /**
     * Convert Markdown to HTML using Parsedown.
     *
     * Images are stripped before conversion since they are not useful
     * inside the WordPress plugin-information modal.
     *
     * IMPORTANT: WordPress install_plugin_information() sanitizes section
     * content with wp_kses() using $plugins_allowedtags — which does NOT
     * include <table>, <tr>, <th>, <td>. Tables generated by Parsedown
     * are therefore converted to <div>/<span> structures via tables_to_divs()
     * and styled with CSS injected through admin_head (see plugin_info_css()).
     */
    private static function markdown_to_html(string $markdown): string
    {
        if ('' === $markdown) {
            return '';
        }

        // Remove images (not useful in the modal).
        $markdown = preg_replace('/!\[[^\]]*\]\([^\)]+\)/', '', $markdown);

        if (!class_exists('Parsedown')) {
            require_once __DIR__ . '/Parsedown.php';
        }

        $parsedown = new Parsedown();
        $parsedown->setSafeMode(true);

        $html = $parsedown->text($markdown);

        // Convert <table> to wp_kses-safe <div>/<span> structures.
        $html = self::tables_to_divs($html);

        return $html;
    }

    /**
     * Convert HTML tables to div/span structures compatible with wp_kses.
     *
     * WordPress plugin info modal only allows: div (with class), span (with class),
     * p, strong, em, code, a, ul, ol, li, h1-h6, pre, br, img.
     * Table elements (table, thead, tbody, tr, th, td) are stripped entirely.
     *
     * This method replaces <table> with CSS-table divs:
     * - <div class="md-table">  → display: table
     * - <div class="md-tr">     → display: table-row
     * - <div class="md-tr md-th"> → header row (bold + background)
     * - <span>                  → display: table-cell
     *
     * The corresponding CSS is injected by plugin_info_css() via admin_head.
     *
     * @param string $html HTML containing <table> elements.
     * @return string HTML with tables replaced by styled div/span.
     */
    private static function tables_to_divs(string $html): string
    {
        return preg_replace_callback('/<table>(.*?)<\/table>/s', function ($m) {
            $table_html = $m[1];
            $output = '<div class="md-table">';

            // Extract all rows (from thead and tbody).
            preg_match_all('/<tr>(.*?)<\/tr>/s', $table_html, $rows);

            foreach ($rows[1] as $idx => $row_content) {
                $is_header = (0 === $idx && strpos($table_html, '<thead>') !== false);
                $row_class = $is_header ? 'md-tr md-th' : 'md-tr';

                // Extract cell contents (th or td).
                preg_match_all('/<t[hd]>(.*?)<\/t[hd]>/s', $row_content, $cells);

                $output .= '<div class="' . $row_class . '">';
                foreach ($cells[1] as $cell) {
                    $output .= '<span>' . $cell . '</span>';
                }
                $output .= '</div>';
            }

            $output .= '</div>';
            return $output;
        }, $html);
    }

    /**
     * Rename the extracted folder to match the expected plugin folder name.
     *
     * GitHub zipball contains a folder named "username-repo-hash" which breaks
     * WordPress plugin updates. This filter renames it to the correct folder name.
     *
     * @param string      $source        File source location.
     * @param string      $remote_source Remote file source location.
     * @param WP_Upgrader $upgrader      WP_Upgrader instance.
     * @param array       $hook_extra    Extra arguments passed to hooked filters.
     * @return string|WP_Error The corrected source path or WP_Error on failure.
     */
    public static function fix_folder_name($source, $remote_source, $upgrader, $hook_extra)
    {
        global $wp_filesystem;

        // Only process plugin updates
        if (!isset($hook_extra['plugin'])) {
            return $source;
        }

        // Check if this is our plugin
        if (self::PLUGIN_FILE !== $hook_extra['plugin']) {
            return $source;
        }

        // Expected folder name (extract from PLUGIN_FILE)
        $correct_folder = dirname(self::PLUGIN_FILE);

        // Get the current folder name from source path
        $source_folder = basename(untrailingslashit($source));

        // If already correct, no action needed
        if ($source_folder === $correct_folder) {
            return $source;
        }

        // Build new source path with correct folder name
        $new_source = trailingslashit($remote_source) . $correct_folder . '/';

        // Rename the folder
        if ($wp_filesystem && $wp_filesystem->move($source, $new_source)) {
            return $new_source;
        }

        // Attempt copy+delete fallback if move failed
        if ($wp_filesystem && $wp_filesystem->copy($source, $new_source, true) && $wp_filesystem->delete($source, true)) {
            return $new_source;
        }

        // Log for debugging without fatals in production
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '%s updater: failed to rename update folder from %s to %s',
                self::PLUGIN_NAME,
                $source,
                $new_source
            ));
        }

        return new WP_Error(
            'rename_failed',
            __('Unable to rename the update folder. Please retry or update manually.', self::TEXT_DOMAIN)
        );
    }
}

// Initialize the updater
FSM_GitHub_Updater::init();
