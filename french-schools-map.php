<?php

/**
 * Plugin Name: French Schools Map
 * Plugin URI: https://github.com/guilamu/french-schools-map
 * Description: Carte interactive des établissements scolaires français basée sur OpenStreetMap et les données open data du Ministère de l'Éducation Nationale.
 * Version: 1.3.0
 * Author: Guilamu
 * Author URI: https://github.com/guilamu
 * Text Domain: french-schools-map
 * Domain Path: /languages
 * Update URI: https://github.com/guilamu/french-schools-map/
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: AGPL-3.0
 * License URI: https://www.gnu.org/licenses/agpl-3.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FSM_VERSION', '1.3.0');
define('FSM_PLUGIN_FILE', __FILE__);
define('FSM_PATH', plugin_dir_path(__FILE__));
define('FSM_URL', plugin_dir_url(__FILE__));

// ─── Autoload includes ───────────────────────────────────────────────
require_once FSM_PATH . 'includes/class-fsm-academies.php';
require_once FSM_PATH . 'includes/class-fsm-local-db.php';
require_once FSM_PATH . 'includes/class-fsm-rest-api.php';
require_once FSM_PATH . 'includes/class-fsm-admin.php';
require_once FSM_PATH . 'includes/class-github-updater.php';

// ─── Translations ────────────────────────────────────────────────────
function fsm_load_textdomain()
{
    load_plugin_textdomain(
        'french-schools-map',
        false,
        dirname(plugin_basename(FSM_PLUGIN_FILE)) . '/languages'
    );
}
add_action('init', 'fsm_load_textdomain', 1);

// ─── Activation ──────────────────────────────────────────────────────
register_activation_hook(FSM_PLUGIN_FILE, 'fsm_activate');
function fsm_activate()
{
    FSM_Local_DB::create_table();
    FSM_Local_DB::schedule_sync();

    // Register the custom monthly interval before scheduling.
    fsm_register_cron_intervals();

    // Trigger an initial sync if no data yet.
    if (!FSM_Local_DB::has_data()) {
        wp_schedule_single_event(time() + 10, FSM_Local_DB::CRON_HOOK);
    }
}

// ─── Deactivation ────────────────────────────────────────────────────
register_deactivation_hook(FSM_PLUGIN_FILE, 'fsm_deactivate');
function fsm_deactivate()
{
    FSM_Local_DB::unschedule_sync();
}

// ─── Cron interval ───────────────────────────────────────────────────
add_filter('cron_schedules', 'fsm_register_cron_intervals');
function fsm_register_cron_intervals($schedules = array())
{
    $schedules['fsm_monthly'] = array(
        'interval' => 30 * DAY_IN_SECONDS,
        'display'  => __('Once Monthly (FSM)', 'french-schools-map'),
    );
    return $schedules;
}

// ─── Cron hook ───────────────────────────────────────────────────────
add_action(FSM_Local_DB::CRON_HOOK, array('FSM_Local_DB', 'sync'));

// ─── Bug Reporter integration ─────────────────────────────────────────
add_action('plugins_loaded', function () {
    if (class_exists('Guilamu_Bug_Reporter')) {
        Guilamu_Bug_Reporter::register(array(
            'slug'        => 'french-schools-map',
            'name'        => 'French Schools Map',
            'version'     => FSM_VERSION,
            'github_repo' => 'guilamu/french-schools-map',
        ));
    }
}, 20);

add_filter('plugin_row_meta', 'fsm_plugin_row_meta', 10, 2);
function fsm_plugin_row_meta($links, $file)
{
    if (plugin_basename(FSM_PLUGIN_FILE) !== $file) {
        return $links;
    }

    if (class_exists('Guilamu_Bug_Reporter')) {
        $links[] = sprintf(
            '<a href="#" class="guilamu-bug-report-btn" data-plugin-slug="french-schools-map" data-plugin-name="%s">%s</a>',
            esc_attr__('French Schools Map', 'french-schools-map'),
            esc_html__('🐛 Report a Bug', 'french-schools-map')
        );
    } else {
        $links[] = sprintf(
            '<a href="%s" target="_blank">%s</a>',
            'https://github.com/guilamu/guilamu-bug-reporter/releases',
            esc_html__('🐛 Report a Bug (install Bug Reporter)', 'french-schools-map')
        );
    }

    return $links;
}

// ─── Init REST API ───────────────────────────────────────────────────
add_action('rest_api_init', array('FSM_REST_API', 'register_routes'));

// ─── Admin ───────────────────────────────────────────────────────────
if (is_admin()) {
    FSM_Admin::init();
}

// ─── Shortcode ───────────────────────────────────────────────────────
add_shortcode('french_schools_map', 'fsm_render_shortcode');

/**
 * Render the [french_schools_map] shortcode.
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output.
 */
function fsm_render_shortcode($atts = array())
{
    $atts = shortcode_atts(
        array(
            'height'                => '600px',
            'center_lat'            => '46.603354',
            'center_lng'            => '1.888334',
            'zoom'                  => '6',
            'types'                 => 'all',
            'departement'           => get_option('fsm_default_departement', 'all'),
            'academie'              => get_option('fsm_default_academie', 'all'),
            'statut'                => 'all',
            'education_prioritaire' => 'all',
            'circonscription'       => 'all',
            'show_filters'          => 'true',
            'show_search'           => 'true',
            'show_filter_academie'  => 'true',
            'show_filter_dept'      => 'true',
            'show_filter_statut'    => 'true',
            'show_filter_types'     => 'true',
            'show_filter_ep'        => 'true',
            'cluster'               => 'false',
            'max_zoom'              => '18',
            'tile_url'              => '',
            'show_circo_zones'      => 'true',
            'show_transport'        => 'false',
        ),
        $atts,
        'french_schools_map'
    );

    // Global plugin settings always override stale block attribute values.
    // This ensures that a change made in the plugin Settings page takes
    // effect immediately on every page/post, without requiring the editor
    // to re-open and re-save each Gutenberg block.
    $global_dept   = get_option('fsm_default_departement', 'all');
    $global_acad   = get_option('fsm_default_academie',    'all');
    $global_types  = get_option('fsm_default_types',       'all');
    $global_statut = get_option('fsm_default_statut',      'all');
    $global_ep     = get_option('fsm_default_ep',          'all');

    if ($global_dept !== 'all') {
        $atts['departement'] = $global_dept;
        $atts['academie']    = 'all';
    } elseif ($global_acad !== 'all') {
        $atts['academie']    = $global_acad;
        $atts['departement'] = 'all';
    }
    if ($global_types  !== 'all') $atts['types']                = $global_types;
    if ($global_statut !== 'all') $atts['statut']               = $global_statut;
    if ($global_ep     !== 'all') $atts['education_prioritaire'] = $global_ep;

    // Enqueue frontend assets.
    fsm_enqueue_frontend_assets();

    // Sanitize booleans.
    $show_filters = filter_var($atts['show_filters'], FILTER_VALIDATE_BOOLEAN);
    $show_search  = filter_var($atts['show_search'], FILTER_VALIDATE_BOOLEAN);
    $cluster      = filter_var($atts['cluster'], FILTER_VALIDATE_BOOLEAN);
    $show_circo   = filter_var($atts['show_circo_zones'], FILTER_VALIDATE_BOOLEAN);

    // Per-filter visibility.
    $show_f_academie = filter_var($atts['show_filter_academie'], FILTER_VALIDATE_BOOLEAN);
    $show_f_dept     = filter_var($atts['show_filter_dept'], FILTER_VALIDATE_BOOLEAN);
    $show_f_statut   = filter_var($atts['show_filter_statut'], FILTER_VALIDATE_BOOLEAN);
    $show_f_types    = filter_var($atts['show_filter_types'], FILTER_VALIDATE_BOOLEAN);
    $show_f_ep       = filter_var($atts['show_filter_ep'], FILTER_VALIDATE_BOOLEAN);

    // Build data attributes.
    $map_id   = 'fsm-map-' . wp_unique_id();
    $config   = array(
        'centerLat'             => (float) $atts['center_lat'],
        'centerLng'             => (float) $atts['center_lng'],
        'zoom'                  => (int) $atts['zoom'],
        'maxZoom'               => (int) $atts['max_zoom'],
        'types'                 => $atts['types'],
        'departement'           => $atts['departement'],
        'academie'              => $atts['academie'],
        'academies'             => FSM_Academies::get_map(),
        'statut'                => $atts['statut'],
        'educationPrioritaire'  => $atts['education_prioritaire'],
        'circonscription'       => $atts['circonscription'],
        'showFilters'           => $show_filters,
        'showSearch'            => $show_search,
        'cluster'               => $cluster,
        'tileUrl'               => $atts['tile_url'],
        'showCircoZones'        => $show_circo,
        'showTransport'         => filter_var($atts['show_transport'], FILTER_VALIDATE_BOOLEAN),
        'restUrl'               => esc_url_raw(rest_url('fsm/v1/')),
        'nonce'                 => wp_create_nonce('wp_rest'),
    );

    $height = esc_attr($atts['height']);

    ob_start();
?>
    <div class="fsm-wrapper" id="<?php echo esc_attr($map_id); ?>-wrapper">
        <?php if ($show_filters) : ?>
            <div class="fsm-filters" id="<?php echo esc_attr($map_id); ?>-filters">
                <div class="fsm-filters-row">
                    <?php if ($show_search) : ?>
                        <div class="fsm-filter-group fsm-search-group">
                            <label for="<?php echo esc_attr($map_id); ?>-search"><?php esc_html_e('Rechercher', 'french-schools-map'); ?></label>
                            <input type="text" id="<?php echo esc_attr($map_id); ?>-search" class="fsm-search-input" placeholder="<?php esc_attr_e('Nom ou ville…', 'french-schools-map'); ?>" />
                        </div>
                    <?php endif; ?>

                    <?php if ($show_f_academie) : ?>
                        <div class="fsm-filter-group">
                            <label for="<?php echo esc_attr($map_id); ?>-academie"><?php esc_html_e('Académie', 'french-schools-map'); ?></label>
                            <select id="<?php echo esc_attr($map_id); ?>-academie" class="fsm-select-academie">
                                <option value="all"><?php esc_html_e('Toutes', 'french-schools-map'); ?></option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_f_dept) : ?>
                        <div class="fsm-filter-group">
                            <label for="<?php echo esc_attr($map_id); ?>-dept"><?php esc_html_e('Département', 'french-schools-map'); ?></label>
                            <select id="<?php echo esc_attr($map_id); ?>-dept" class="fsm-select-dept">
                                <option value="all"><?php esc_html_e('Tous', 'french-schools-map'); ?></option>
                            </select>
                        </div>

                        <div class="fsm-filter-group fsm-filter-circo" style="display:none;">
                            <label for="<?php echo esc_attr($map_id); ?>-circo"><?php esc_html_e('Circonscription', 'french-schools-map'); ?></label>
                            <select id="<?php echo esc_attr($map_id); ?>-circo" class="fsm-select-circo">
                                <option value="all"><?php esc_html_e('Toutes', 'french-schools-map'); ?></option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_f_statut) : ?>
                        <div class="fsm-filter-group">
                            <label for="<?php echo esc_attr($map_id); ?>-statut"><?php esc_html_e('Statut', 'french-schools-map'); ?></label>
                            <select id="<?php echo esc_attr($map_id); ?>-statut" class="fsm-select-statut">
                                <option value="all"><?php esc_html_e('Tous', 'french-schools-map'); ?></option>
                                <option value="Public"><?php esc_html_e('Public', 'french-schools-map'); ?></option>
                                <option value="Privé"><?php esc_html_e('Privé', 'french-schools-map'); ?></option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_f_types) : ?>
                        <div class="fsm-filter-group fsm-filter-types">
                            <span class="fsm-filter-label"><?php esc_html_e('Types', 'french-schools-map'); ?></span>
                            <label><input type="checkbox" class="fsm-type-cb" value="Ecole" checked /> <?php esc_html_e('Écoles', 'french-schools-map'); ?></label>
                            <label><input type="checkbox" class="fsm-type-cb" value="Collège" checked /> <?php esc_html_e('Collèges', 'french-schools-map'); ?></label>
                            <label><input type="checkbox" class="fsm-type-cb" value="Lycée" checked /> <?php esc_html_e('Lycées', 'french-schools-map'); ?></label>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_f_ep) : ?>
                        <div class="fsm-filter-group">
                            <label for="<?php echo esc_attr($map_id); ?>-ep"><?php esc_html_e('Éducation prioritaire', 'french-schools-map'); ?></label>
                            <select id="<?php echo esc_attr($map_id); ?>-ep" class="fsm-select-ep">
                                <option value="all"><?php esc_html_e('Tous', 'french-schools-map'); ?></option>
                                <option value="REP"><?php esc_html_e('REP', 'french-schools-map'); ?></option>
                                <option value="REP+"><?php esc_html_e('REP+', 'french-schools-map'); ?></option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <button type="button" class="fsm-btn-transport" title="<?php esc_attr_e('Transports en commun', 'french-schools-map'); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="16" rx="2"/><circle cx="9" cy="15" r="1"/><circle cx="15" cy="15" r="1"/><path d="M4 11h16"/><path d="M12 3v8"/></svg>
                    </button>
                    <button type="button" class="fsm-btn-locate" title="<?php esc_attr_e('Me localiser', 'french-schools-map'); ?>">
                        <span class="dashicons dashicons-location"></span>
                    </button>
                    <button type="button" class="fsm-btn-fullscreen" title="<?php esc_attr_e('Plein écran', 'french-schools-map'); ?>">
                        <span class="dashicons dashicons-fullscreen-alt"></span>
                    </button>
                </div>
                <div class="fsm-stats">
                    <span class="fsm-stats-count"></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$show_filters) : ?>
            <button type="button" class="fsm-btn-fullscreen fsm-btn-fullscreen-standalone" title="<?php esc_attr_e('Plein écran', 'french-schools-map'); ?>">
                <span class="dashicons dashicons-fullscreen-alt"></span>
            </button>
        <?php endif; ?>

        <div class="fsm-map" id="<?php echo esc_attr($map_id); ?>" style="height:<?php echo $height; ?>;"></div>

    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof FSM_Map !== 'undefined') {
                new FSM_Map('<?php echo esc_js($map_id); ?>', <?php echo wp_json_encode($config); ?>);
            }
        });
    </script>
<?php
    return ob_get_clean();
}

// ─── Enqueue frontend assets ─────────────────────────────────────────
function fsm_enqueue_frontend_assets()
{
    // Leaflet CSS + JS (CDN).
    wp_enqueue_style(
        'leaflet',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
        array(),
        '1.9.4'
    );
    wp_enqueue_script(
        'leaflet',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
        array(),
        '1.9.4',
        true
    );

    // Leaflet MarkerCluster.
    wp_enqueue_style(
        'leaflet-markercluster',
        'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css',
        array('leaflet'),
        '1.5.3'
    );
    wp_enqueue_style(
        'leaflet-markercluster-default',
        'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css',
        array('leaflet-markercluster'),
        '1.5.3'
    );
    wp_enqueue_script(
        'leaflet-markercluster',
        'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js',
        array('leaflet'),
        '1.5.3',
        true
    );

    // Plugin CSS + JS.
    wp_enqueue_style(
        'fsm-map',
        FSM_URL . 'assets/css/fsm-map.css',
        array('leaflet', 'leaflet-markercluster', 'dashicons'),
        FSM_VERSION
    );
    wp_enqueue_script(
        'fsm-map',
        FSM_URL . 'assets/js/fsm-map.js',
        array('leaflet', 'leaflet-markercluster'),
        FSM_VERSION,
        true
    );

    wp_localize_script('fsm-map', 'fsmMapI18n', array(
        'legend'          => __('Legend', 'french-schools-map'),
        'loading'         => __('Loading…', 'french-schools-map'),
        'loadError'       => __('Loading error.', 'french-schools-map'),
        'moreInfo'        => __('More info…', 'french-schools-map'),
        'error'           => __('Error', 'french-schools-map'),
        'directions'      => __('Directions', 'french-schools-map'),
        'exitFullscreen'  => __('Exit fullscreen', 'french-schools-map'),
        'fullscreen'      => __('Fullscreen', 'french-schools-map'),
        /* translators: %1$s = number of schools shown, %2$s = total number */
        'statsFormat'     => __('%1$s school(s) shown out of %2$s', 'french-schools-map'),
        'showTransport'   => __('Show public transport', 'french-schools-map'),
        'hideTransport'   => __('Hide public transport', 'french-schools-map'),
    ));
}

// ─── Gutenberg Block ─────────────────────────────────────────────────
add_action('init', 'fsm_register_block');
function fsm_register_block()
{
    if (!function_exists('register_block_type')) {
        return;
    }

    wp_register_script(
        'fsm-block-editor',
        FSM_URL . 'assets/js/fsm-block.js',
        array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'),
        FSM_VERSION,
        true
    );

    // Pass dropdown options to the block editor JS.
    $block_departments = FSM_Local_DB::has_data() ? FSM_Local_DB::get_departments() : array();
    $block_academies   = FSM_Academies::get_names();
    wp_localize_script('fsm-block-editor', 'fsmBlockData', array(
        'departments' => $block_departments,
        'academies'   => $block_academies,
        'defaultDept' => get_option('fsm_default_departement', 'all'),
        'defaultAcad' => get_option('fsm_default_academie', 'all'),
        'restUrl'     => esc_url_raw(rest_url('fsm/v1/')),
        'nonce'       => wp_create_nonce('wp_rest'),
    ));

    register_block_type('french-schools-map/map', array(
        'editor_script'   => 'fsm-block-editor',
        'render_callback' => 'fsm_render_shortcode',
        'attributes'      => array(
            'height' => array(
                'type'    => 'string',
                'default' => '600px',
            ),
            'center_lat' => array(
                'type'    => 'string',
                'default' => '46.603354',
            ),
            'center_lng' => array(
                'type'    => 'string',
                'default' => '1.888334',
            ),
            'zoom' => array(
                'type'    => 'string',
                'default' => '6',
            ),
            'types' => array(
                'type'    => 'string',
                'default' => 'all',
            ),
            'departement' => array(
                'type'    => 'string',
                'default' => 'all',
            ),
            'academie' => array(
                'type'    => 'string',
                'default' => 'all',
            ),
            'statut' => array(
                'type'    => 'string',
                'default' => 'all',
            ),
            'circonscription' => array(
                'type'    => 'string',
                'default' => 'all',
            ),
            'show_filters' => array(
                'type'    => 'string',
                'default' => 'true',
            ),
            'show_search' => array(
                'type'    => 'string',
                'default' => 'true',
            ),
            'show_filter_academie' => array(
                'type'    => 'string',
                'default' => 'true',
            ),
            'show_filter_dept' => array(
                'type'    => 'string',
                'default' => 'true',
            ),
            'show_filter_statut' => array(
                'type'    => 'string',
                'default' => 'true',
            ),
            'show_filter_types' => array(
                'type'    => 'string',
                'default' => 'true',
            ),
            'show_filter_ep' => array(
                'type'    => 'string',
                'default' => 'true',
            ),
            'cluster' => array(
                'type'    => 'string',
                'default' => 'false',
            ),
            'show_circo_zones' => array(
                'type'    => 'string',
                'default' => 'true',
            ),
        ),
    ));
}
