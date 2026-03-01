<?php

/**
 * Admin page for French Schools Map.
 *
 * @package French_Schools_Map
 */

if (!defined('ABSPATH')) {
    exit;
}

class FSM_Admin
{
    /**
     * Initialise admin hooks.
     */
    public static function init()
    {
        add_action('admin_menu', array(__CLASS__, 'add_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('wp_ajax_fsm_sync', array(__CLASS__, 'ajax_sync'));
        add_action('wp_ajax_fsm_sync_status', array(__CLASS__, 'ajax_sync_status'));
    }

    /**
     * Register admin menu page.
     */
    public static function add_menu()
    {
        add_menu_page(
            __('French Schools Map', 'french-schools-map'),
            __('Schools Map', 'french-schools-map'),
            'manage_options',
            'french-schools-map',
            array(__CLASS__, 'render_page'),
            'dashicons-location-alt',
            80
        );
    }

    /**
     * Enqueue admin assets only on our page.
     */
    public static function enqueue_assets($hook)
    {
        if ($hook !== 'toplevel_page_french-schools-map') {
            return;
        }

        wp_enqueue_style(
            'fsm-admin',
            FSM_URL . 'assets/css/fsm-admin.css',
            array(),
            FSM_VERSION
        );

        wp_enqueue_script(
            'fsm-admin',
            FSM_URL . 'assets/js/fsm-admin.js',
            array('jquery'),
            FSM_VERSION,
            true
        );

        wp_localize_script('fsm-admin', 'fsmAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('fsm_admin'),
            'i18n'    => array(
                'syncing'      => __('Syncing…', 'french-schools-map'),
                'syncSuccess'  => __('Sync completed successfully!', 'french-schools-map'),
                'syncError'    => __('An error occurred during sync.', 'french-schools-map'),
                'confirmSync'  => __('Start sync now? This may take several minutes.', 'french-schools-map'),
            ),
        ));
    }

    /**
     * Register settings.
     */
    public static function register_settings()
    {
        $text_field = array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'all');
        register_setting('fsm_settings', 'fsm_default_departement', $text_field);
        register_setting('fsm_settings', 'fsm_default_academie',    $text_field);
        register_setting('fsm_settings', 'fsm_default_types',       $text_field);
        register_setting('fsm_settings', 'fsm_default_statut',      $text_field);
        register_setting('fsm_settings', 'fsm_default_ep',          $text_field);
    }

    /**
     * Render the admin page.
     */
    public static function render_page()
    {
        // Handle settings save.
        if (isset($_POST['fsm_save_settings']) && check_admin_referer('fsm_settings_nonce')) {
            $dept   = sanitize_text_field($_POST['fsm_default_departement'] ?? 'all');
            $acad   = sanitize_text_field($_POST['fsm_default_academie']    ?? 'all');
            $types  = sanitize_text_field($_POST['fsm_default_types']       ?? 'all');
            $statut = sanitize_text_field($_POST['fsm_default_statut']      ?? 'all');
            $ep     = sanitize_text_field($_POST['fsm_default_ep']          ?? 'all');

            // Mutual exclusion: if académie is set, clear département and vice-versa.
            if ($acad !== 'all') {
                $dept = 'all';
            }

            update_option('fsm_default_departement', $dept);
            update_option('fsm_default_academie',    $acad);
            update_option('fsm_default_types',       $types);
            update_option('fsm_default_statut',      $statut);
            update_option('fsm_default_ep',          $ep);

            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'french-schools-map') . '</p></div>';
        }

        $status       = FSM_Local_DB::get_status();
        $next         = wp_next_scheduled(FSM_Local_DB::CRON_HOOK);
        $saved_dept   = get_option('fsm_default_departement', 'all');
        $saved_acad   = get_option('fsm_default_academie',    'all');
        $saved_types  = get_option('fsm_default_types',       'all');
        $saved_statut = get_option('fsm_default_statut',      'all');
        $saved_ep     = get_option('fsm_default_ep',          'all');
        $academies    = FSM_Academies::get_names();
        $departments  = FSM_Local_DB::has_data() ? FSM_Local_DB::get_departments() : array();
?>
        <div class="wrap fsm-admin-wrap">
            <h1><?php esc_html_e('French Schools Map', 'french-schools-map'); ?></h1>
            <p class="description"><?php esc_html_e('Carte interactive des établissements scolaires français.', 'french-schools-map'); ?></p>

            <!-- Shortcode help -->
            <div class="fsm-card">
                <h2><?php esc_html_e('Utilisation', 'french-schools-map'); ?></h2>
                <p><?php esc_html_e('Ajoutez la carte dans une page ou un article avec le shortcode :', 'french-schools-map'); ?></p>
                <code>[french_schools_map]</code>
                <p style="margin-top:10px;"><?php esc_html_e('Ou utilisez le bloc « French Schools Map » dans l\'éditeur Gutenberg.', 'french-schools-map'); ?></p>

                <h3><?php esc_html_e('Attributs disponibles', 'french-schools-map'); ?></h3>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Attribut', 'french-schools-map'); ?></th>
                            <th><?php esc_html_e('Défaut', 'french-schools-map'); ?></th>
                            <th><?php esc_html_e('Description', 'french-schools-map'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>height</code></td>
                            <td>600px</td>
                            <td><?php esc_html_e('Hauteur de la carte', 'french-schools-map'); ?></td>
                        </tr>
                        <tr>
                            <td><code>zoom</code></td>
                            <td>6</td>
                            <td><?php esc_html_e('Niveau de zoom initial', 'french-schools-map'); ?></td>
                        </tr>
                        <tr>
                            <td><code>types</code></td>
                            <td>all</td>
                            <td><?php esc_html_e('Types : Ecole, Collège, Lycée (séparés par des virgules)', 'french-schools-map'); ?></td>
                        </tr>
                        <tr>
                            <td><code>departement</code></td>
                            <td>all</td>
                            <td><?php esc_html_e('Nom du département à filtrer', 'french-schools-map'); ?></td>
                        </tr>
                        <tr>
                            <td><code>academie</code></td>
                            <td>all</td>
                            <td><?php esc_html_e('Nom de l\'académie à filtrer', 'french-schools-map'); ?></td>
                        </tr>
                        <tr>
                            <td><code>statut</code></td>
                            <td>all</td>
                            <td><?php esc_html_e('Public ou Privé', 'french-schools-map'); ?></td>
                        </tr>
                        <tr>
                            <td><code>show_filters</code></td>
                            <td>true</td>
                            <td><?php esc_html_e('Afficher le panneau de filtres', 'french-schools-map'); ?></td>
                        </tr>
                        <tr>
                            <td><code>show_search</code></td>
                            <td>true</td>
                            <td><?php esc_html_e('Afficher la barre de recherche', 'french-schools-map'); ?></td>
                        </tr>
                        <tr>
                            <td><code>cluster</code></td>
                            <td>true</td>
                            <td><?php esc_html_e('Activer le clustering de marqueurs', 'french-schools-map'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Default filters settings -->
            <div class="fsm-card">
                <h2><?php esc_html_e('Default settings', 'french-schools-map'); ?></h2>
                <p class="description"><?php esc_html_e('These values are used as defaults when the shortcode or block does not specify an attribute.', 'french-schools-map'); ?></p>

                <form method="post">
                    <?php wp_nonce_field('fsm_settings_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="fsm_default_academie"><?php esc_html_e('Default academy', 'french-schools-map'); ?></label></th>
                            <td>
                                <select name="fsm_default_academie" id="fsm_default_academie">
                                    <option value="all"><?php esc_html_e('All (no filter)', 'french-schools-map'); ?></option>
                                    <?php foreach ($academies as $acad) : ?>
                                        <option value="<?php echo esc_attr($acad); ?>" <?php selected($saved_acad, $acad); ?>><?php echo esc_html($acad); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e('If an academy is selected, it automatically groups the corresponding departments.', 'french-schools-map'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="fsm_default_departement"><?php esc_html_e('Default department', 'french-schools-map'); ?></label></th>
                            <td>
                                <select name="fsm_default_departement" id="fsm_default_departement">
                                    <option value="all"><?php esc_html_e('All (no filter)', 'french-schools-map'); ?></option>
                                    <?php foreach ($departments as $dept) : ?>
                                        <option value="<?php echo esc_attr($dept); ?>" <?php selected($saved_dept, $dept); ?>><?php echo esc_html($dept); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e('The department takes priority over the academy. If a department is chosen, the academy is ignored.', 'french-schools-map'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="fsm_default_types"><?php esc_html_e('Default types', 'french-schools-map'); ?></label></th>
                            <td>
                                <select name="fsm_default_types" id="fsm_default_types">
                                    <option value="all" <?php selected($saved_types, 'all'); ?>><?php esc_html_e('All (schools + middle + high)', 'french-schools-map'); ?></option>
                                    <option value="Ecole" <?php selected($saved_types, 'Ecole'); ?>><?php esc_html_e('Schools only', 'french-schools-map'); ?></option>
                                    <option value="Coll&#232;ge" <?php selected($saved_types, 'Coll&#232;ge'); ?>><?php esc_html_e('Middle schools only', 'french-schools-map'); ?></option>
                                    <option value="Lyc&#233;e" <?php selected($saved_types, 'Lyc&#233;e'); ?>><?php esc_html_e('High schools only', 'french-schools-map'); ?></option>
                                    <option value="Ecole,Coll&#232;ge" <?php selected($saved_types, 'Ecole,Coll&#232;ge'); ?>><?php esc_html_e('Schools + Middle schools', 'french-schools-map'); ?></option>
                                    <option value="Coll&#232;ge,Lyc&#233;e" <?php selected($saved_types, 'Coll&#232;ge,Lyc&#233;e'); ?>><?php esc_html_e('Middle + High schools', 'french-schools-map'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="fsm_default_statut"><?php esc_html_e('Default status', 'french-schools-map'); ?></label></th>
                            <td>
                                <select name="fsm_default_statut" id="fsm_default_statut">
                                    <option value="all" <?php selected($saved_statut, 'all'); ?>><?php esc_html_e('All (public + private)', 'french-schools-map'); ?></option>
                                    <option value="Public" <?php selected($saved_statut, 'Public'); ?>><?php esc_html_e('Public only', 'french-schools-map'); ?></option>
                                    <option value="Priv&#233;" <?php selected($saved_statut, 'Priv&#233;'); ?>><?php esc_html_e('Private only', 'french-schools-map'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="fsm_default_ep"><?php esc_html_e('Default priority education', 'french-schools-map'); ?></label></th>
                            <td>
                                <select name="fsm_default_ep" id="fsm_default_ep">
                                    <option value="all" <?php selected($saved_ep, 'all'); ?>><?php esc_html_e('All', 'french-schools-map'); ?></option>
                                    <option value="REP" <?php selected($saved_ep, 'REP'); ?>>REP</option>
                                    <option value="REP+" <?php selected($saved_ep, 'REP+'); ?>>REP+</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <script>
                        (function() {
                            var acad = document.getElementById('fsm_default_academie');
                            var dept = document.getElementById('fsm_default_departement');
                            if (!acad || !dept) return;
                            acad.addEventListener('change', function() {
                                if (acad.value !== 'all') dept.value = 'all';
                            });
                            dept.addEventListener('change', function() {
                                if (dept.value !== 'all') acad.value = 'all';
                            });
                        })();
                    </script>
                    <p>
                        <button type="submit" name="fsm_save_settings" class="button button-primary">
                            <?php esc_html_e('Save settings', 'french-schools-map'); ?>
                        </button>
                    </p>
                </form>
            </div>


            <!-- Sync status -->
            <div class="fsm-card">
                <h2><?php esc_html_e('Synchronisation des données', 'french-schools-map'); ?></h2>

                <table class="form-table fsm-status-table" id="fsm-status-table">
                    <tr>
                        <th><?php esc_html_e('Statut', 'french-schools-map'); ?></th>
                        <td id="fsm-sync-status">
                            <?php echo esc_html(self::format_status($status['status'])); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Dernière synchronisation', 'french-schools-map'); ?></th>
                        <td id="fsm-last-sync">
                            <?php
                            if ($status['last_sync']) {
                                echo esc_html(
                                    date_i18n(
                                        get_option('date_format') . ' ' . get_option('time_format'),
                                        $status['last_sync']
                                    )
                                );
                            } else {
                                esc_html_e('Jamais', 'french-schools-map');
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Enregistrements', 'french-schools-map'); ?></th>
                        <td id="fsm-record-count">
                            <?php echo esc_html(number_format_i18n($status['record_count'])); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Prochaine synchronisation', 'french-schools-map'); ?></th>
                        <td>
                            <?php
                            if ($next) {
                                echo esc_html(
                                    date_i18n(
                                        get_option('date_format') . ' ' . get_option('time_format'),
                                        $next
                                    )
                                );
                            } else {
                                esc_html_e('Non planifiée', 'french-schools-map');
                            }
                            ?>
                        </td>
                    </tr>
                    <?php if (!empty($status['error'])) : ?>
                        <tr>
                            <th><?php esc_html_e('Erreur', 'french-schools-map'); ?></th>
                            <td class="fsm-error"><?php echo esc_html($status['error']); ?></td>
                        </tr>
                    <?php endif; ?>
                </table>

                <p>
                    <button type="button" class="button button-primary" id="fsm-sync-btn">
                        <?php esc_html_e('Synchroniser maintenant', 'french-schools-map'); ?>
                    </button>
                    <span id="fsm-sync-spinner" class="spinner" style="float:none;"></span>
                    <span id="fsm-sync-message"></span>
                </p>
                <p class="description">
                    <?php esc_html_e('La synchronisation télécharge l\'intégralité de l\'annuaire (~69 000 établissements) depuis le portail Open Data du Ministère de l\'Éducation Nationale. Cette opération peut prendre plusieurs minutes.', 'french-schools-map'); ?>
                </p>
            </div>
        </div>
<?php
    }

    /**
     * Format sync status label.
     */
    private static function format_status($status)
    {
        $labels = array(
            'idle'    => __('En attente', 'french-schools-map'),
            'running' => __('En cours…', 'french-schools-map'),
            'success' => __('Succès', 'french-schools-map'),
            'error'   => __('Erreur', 'french-schools-map'),
        );
        return $labels[$status] ?? $status;
    }

    /**
     * AJAX: trigger sync.
     */
    public static function ajax_sync()
    {
        check_ajax_referer('fsm_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $result = FSM_Local_DB::sync();

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(FSM_Local_DB::get_status());
    }

    /**
     * AJAX: get sync status.
     */
    public static function ajax_sync_status()
    {
        check_ajax_referer('fsm_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(FSM_Local_DB::get_status());
    }
}
