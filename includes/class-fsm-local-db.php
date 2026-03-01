<?php
/**
 * Local database for French schools map data.
 *
 * Downloads the full dataset CSV monthly and stores it in a custom table
 * so the map can render without hitting the remote API on every page load.
 *
 * @package French_Schools_Map
 */

if (!defined('ABSPATH')) {
    exit;
}

class FSM_Local_DB
{
    const TABLE_NAME       = 'fsm_schools';
    const OPTION_LAST_SYNC = 'fsm_last_sync';
    const OPTION_STATUS    = 'fsm_sync_status';
    const OPTION_COUNT     = 'fsm_record_count';
    const OPTION_ERROR     = 'fsm_sync_error';
    const CRON_HOOK        = 'fsm_monthly_sync';
    const MIN_VALID        = 50000;
    const BATCH_SIZE       = 500;

    /**
     * CSV export URL – all fields we need including lat/lng.
     */
    const EXPORT_URL = 'https://data.education.gouv.fr/api/explore/v2.1/catalog/datasets/fr-en-annuaire-education/exports/csv?select=identifiant_de_l_etablissement%2Cnom_etablissement%2Ctype_etablissement%2Clibelle_nature%2Cstatut_public_prive%2Cadresse_1%2Ccode_postal%2Cnom_commune%2Clibelle_departement%2Ctelephone%2Cmail%2Cappartenance_education_prioritaire%2Cnom_circonscription%2Ccode_circonscription%2Clatitude%2Clongitude&delimiter=%3B';

    // ──────────────────────────────────────────────────────────────────
    // Table management
    // ──────────────────────────────────────────────────────────────────

    public static function table_name()
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    public static function create_table()
    {
        global $wpdb;
        $table           = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            identifiant VARCHAR(20) NOT NULL DEFAULT '',
            nom_etablissement VARCHAR(255) NOT NULL DEFAULT '',
            type_etablissement VARCHAR(100) NOT NULL DEFAULT '',
            libelle_nature VARCHAR(255) NOT NULL DEFAULT '',
            statut_public_prive VARCHAR(10) NOT NULL DEFAULT '',
            adresse VARCHAR(255) NOT NULL DEFAULT '',
            code_postal VARCHAR(10) NOT NULL DEFAULT '',
            nom_commune VARCHAR(100) NOT NULL DEFAULT '',
            libelle_departement VARCHAR(100) NOT NULL DEFAULT '',
            telephone VARCHAR(30) NOT NULL DEFAULT '',
            mail VARCHAR(255) NOT NULL DEFAULT '',
            education_prioritaire VARCHAR(50) NOT NULL DEFAULT '',
            nom_circonscription VARCHAR(255) NOT NULL DEFAULT '',
            code_circonscription VARCHAR(20) NOT NULL DEFAULT '',
            latitude DECIMAL(10,7) DEFAULT NULL,
            longitude DECIMAL(10,7) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY identifiant (identifiant),
            KEY idx_type (type_etablissement),
            KEY idx_departement (libelle_departement),
            KEY idx_statut_dept (statut_public_prive, libelle_departement),
            KEY idx_geo (latitude, longitude)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function drop_table()
    {
        global $wpdb;
        $table = self::table_name();
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
        delete_option(self::OPTION_LAST_SYNC);
        delete_option(self::OPTION_STATUS);
        delete_option(self::OPTION_COUNT);
        delete_option(self::OPTION_ERROR);
    }

    public static function has_data()
    {
        global $wpdb;
        $table = self::table_name();
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        return !empty($count) && (int) $count > 0;
    }

    // ──────────────────────────────────────────────────────────────────
    // Sync / Import
    // ──────────────────────────────────────────────────────────────────

    public static function sync()
    {
        if (get_option(self::OPTION_STATUS) === 'running') {
            return new WP_Error('sync_running', __('A sync is already in progress.', 'french-schools-map'));
        }

        update_option(self::OPTION_STATUS, 'running');
        update_option(self::OPTION_ERROR, '');

        if (function_exists('set_time_limit')) {
            @set_time_limit(600);
        }
        wp_raise_memory_limit('admin');

        // 1. Download CSV.
        $tmp_file = download_url(self::EXPORT_URL, 300);
        if (is_wp_error($tmp_file)) {
            self::sync_failed($tmp_file->get_error_message());
            return $tmp_file;
        }

        // 2. Import via staging table.
        $result = self::import_csv($tmp_file);
        @unlink($tmp_file);

        if (is_wp_error($result)) {
            self::sync_failed($result->get_error_message());
            return $result;
        }

        // 3. Success.
        update_option(self::OPTION_LAST_SYNC, time());
        update_option(self::OPTION_STATUS, 'success');
        update_option(self::OPTION_COUNT, $result);
        update_option(self::OPTION_ERROR, '');

        // 4. Invalidate ALL marker caches.
        self::clear_marker_caches();

        self::log('Sync completed. Records: ' . $result);
        return true;
    }

    private static function import_csv($file_path)
    {
        global $wpdb;

        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return new WP_Error('csv_open', __('Cannot open CSV file.', 'french-schools-map'));
        }

        $header = fgetcsv($handle, 0, ';');
        if (!$header) {
            fclose($handle);
            return new WP_Error('csv_header', __('CSV has no header row.', 'french-schools-map'));
        }

        // Normalise header.
        $header = array_map(function ($col) {
            return strtolower(trim(preg_replace('/\x{FEFF}/u', '', $col)));
        }, $header);

        $column_map = self::map_columns($header);
        if (is_wp_error($column_map)) {
            fclose($handle);
            return $column_map;
        }

        $table   = self::table_name();
        $staging = $table . '_staging';

        $wpdb->query("DROP TABLE IF EXISTS {$staging}");
        $wpdb->query("CREATE TABLE {$staging} LIKE {$table}");

        $total = 0;
        $batch = array();

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $record = self::map_row($row, $column_map);
            if ($record) {
                $batch[] = $record;
                $total++;
            }

            if (count($batch) >= self::BATCH_SIZE) {
                self::insert_batch($staging, $batch);
                $batch = array();
            }
        }

        if (!empty($batch)) {
            self::insert_batch($staging, $batch);
        }

        fclose($handle);

        if ($total < self::MIN_VALID) {
            $wpdb->query("DROP TABLE IF EXISTS {$staging}");
            return new WP_Error(
                'too_few_records',
                sprintf(
                    __('Only %1$d records (minimum %2$d). Aborting.', 'french-schools-map'),
                    $total,
                    self::MIN_VALID
                )
            );
        }

        // Atomic swap.
        $old = $table . '_old';
        $wpdb->query("DROP TABLE IF EXISTS {$old}");
        $wpdb->query("RENAME TABLE {$table} TO {$old}, {$staging} TO {$table}");
        $wpdb->query("DROP TABLE IF EXISTS {$old}");

        return $total;
    }

    private static function map_columns($header)
    {
        $mappings = array(
            'identifiant'         => array('identifiant_de_l_etablissement', "identifiant de l'etablissement", "identifiant de l'établissement"),
            'nom_etablissement'   => array('nom_etablissement', 'nom etablissement'),
            'type_etablissement'  => array('type_etablissement', 'type etablissement'),
            'libelle_nature'      => array('libelle_nature', 'libelle nature', 'libellé nature'),
            'statut_public_prive' => array('statut_public_prive', 'statut public prive', 'statut public privé'),
            'adresse'             => array('adresse_1', 'adresse 1'),
            'code_postal'         => array('code_postal', 'code postal'),
            'nom_commune'         => array('nom_commune', 'nom commune'),
            'libelle_departement' => array('libelle_departement', 'libelle departement', 'libellé département'),
            'telephone'           => array('telephone', 'téléphone'),
            'mail'                => array('mail'),
            'education_prioritaire' => array('appartenance_education_prioritaire', 'appartenance education prioritaire'),
            'nom_circonscription' => array('nom_circonscription', 'nom circonscription'),
            'code_circonscription' => array('code_circonscription', 'code circonscription'),
            'latitude'            => array('latitude'),
            'longitude'           => array('longitude'),
        );

        $column_map = array();
        $missing    = array();

        foreach ($mappings as $db_col => $candidates) {
            $found = false;
            foreach ($candidates as $name) {
                $idx = array_search($name, $header, true);
                if ($idx !== false) {
                    $column_map[$db_col] = $idx;
                    $found = true;
                    break;
                }
            }
            if (!$found && $db_col === 'identifiant') {
                $missing[] = $db_col;
            } elseif (!$found) {
                $column_map[$db_col] = -1;
            }
        }

        if (!empty($missing)) {
            return new WP_Error('csv_missing', sprintf(
                __('CSV missing required columns: %s', 'french-schools-map'),
                implode(', ', $missing)
            ));
        }

        return $column_map;
    }

    private static function map_row($row, $column_map)
    {
        $record = array();
        foreach ($column_map as $db_col => $csv_idx) {
            if ($csv_idx === -1 || !isset($row[$csv_idx])) {
                $record[$db_col] = '';
            } else {
                $record[$db_col] = trim($row[$csv_idx]);
            }
        }

        if (empty($record['identifiant'])) {
            return null;
        }

        // Convert empty lat/lng to NULL for DECIMAL column.
        if ($record['latitude'] === '') {
            $record['latitude'] = null;
        }
        if ($record['longitude'] === '') {
            $record['longitude'] = null;
        }

        return $record;
    }

    private static function insert_batch($table, $records)
    {
        global $wpdb;
        if (empty($records)) {
            return;
        }

        $columns          = array_keys($records[0]);
        $placeholders_row = '(' . implode(',', array_map(function ($col) {
            return ($col === 'latitude' || $col === 'longitude') ? '%s' : '%s';
        }, $columns)) . ')';

        $placeholders = array();
        $values       = array();

        foreach ($records as $record) {
            $placeholders[] = $placeholders_row;
            foreach ($columns as $col) {
                $val = $record[$col];
                // NULL for lat/lng.
                $values[] = $val === null ? null : $val;
            }
        }

        // Build query manually to support NULLs.
        $sql_cols  = '`' . implode('`,`', $columns) . '`';
        $sql_vals  = array();
        $flat_vals = array();
        $i         = 0;

        foreach ($records as $record) {
            $row_ph = array();
            foreach ($columns as $col) {
                $val = $record[$col];
                if ($val === null) {
                    $row_ph[] = 'NULL';
                } else {
                    $row_ph[]   = '%s';
                    $flat_vals[] = $val;
                }
                $i++;
            }
            $sql_vals[] = '(' . implode(',', $row_ph) . ')';
        }

        $sql = "INSERT INTO {$table} ({$sql_cols}) VALUES " . implode(',', $sql_vals);

        if (!empty($flat_vals)) {
            $wpdb->query($wpdb->prepare($sql, $flat_vals));
        } else {
            $wpdb->query($sql);
        }
    }

    private static function sync_failed($message)
    {
        update_option(self::OPTION_STATUS, 'error');
        update_option(self::OPTION_ERROR, $message);
        self::log('Sync failed: ' . $message);
    }

    // ──────────────────────────────────────────────────────────────────
    // Data retrieval for REST API
    // ──────────────────────────────────────────────────────────────────

    /**
     * Get all markers as a compact array.
     *
     * Each item: [lat, lng, type_id, school_id]
     * Type IDs: 1=Ecole, 2=Collège, 3=Lycée, 4=Autre
     *
     * @param array $filters Optional filters.
     * @return array
     */
    public static function get_markers($filters = array())
    {
        global $wpdb;
        $table = self::table_name();

        $where_clauses = array('latitude IS NOT NULL', 'longitude IS NOT NULL');
        $values        = array();

        if (!empty($filters['types']) && $filters['types'] !== 'all') {
            $types      = array_map('trim', explode(',', $filters['types']));
            $ph         = implode(',', array_fill(0, count($types), '%s'));
            $where_clauses[] = "type_etablissement IN ({$ph})";
            $values          = array_merge($values, $types);
        }

        if (!empty($filters['departement']) && $filters['departement'] !== 'all') {
            $where_clauses[] = 'libelle_departement = %s';
            $values[]        = $filters['departement'];
        } elseif (!empty($filters['academie']) && $filters['academie'] !== 'all') {
            // Académie filter: resolve to list of départements.
            $acad_depts = FSM_Academies::get_departments($filters['academie']);
            if (!empty($acad_depts)) {
                $ph = implode(',', array_fill(0, count($acad_depts), '%s'));
                $where_clauses[] = "libelle_departement IN ({$ph})";
                $values = array_merge($values, $acad_depts);
            }
        }

        if (!empty($filters['statut']) && $filters['statut'] !== 'all') {
            $where_clauses[] = 'statut_public_prive = %s';
            $values[]        = $filters['statut'];
        }

        if (!empty($filters['ep']) && $filters['ep'] !== 'all') {
            $where_clauses[] = 'education_prioritaire = %s';
            $values[]        = $filters['ep'];
        }

        if (!empty($filters['search'])) {
            $like            = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_clauses[] = '(nom_etablissement LIKE %s OR nom_commune LIKE %s)';
            $values[]        = $like;
            $values[]        = $like;
        }

        $where = implode(' AND ', $where_clauses);
        $sql   = "SELECT id, latitude, longitude, type_etablissement, nom_etablissement, nom_commune, statut_public_prive FROM {$table} WHERE {$where}";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);

        $type_map = array(
            'Ecole'   => 1,
            'Collège' => 2,
            'Lycée'   => 3,
        );

        $markers = array();
        foreach ($rows as $row) {
            $type_id   = $type_map[$row['type_etablissement']] ?? 4;
            $markers[] = array(
                (float) $row['latitude'],
                (float) $row['longitude'],
                $type_id,
                (int) $row['id'],
                $row['nom_etablissement'],
                $row['nom_commune'],
                $row['statut_public_prive'],
            );
        }

        return $markers;
    }

    /**
     * Get full details by ID.
     *
     * @param int $id School row ID.
     * @return array|null
     */
    public static function get_school($id)
    {
        global $wpdb;
        $table = self::table_name();
        $row   = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ), ARRAY_A);

        return $row ?: null;
    }

    /**
     * Get full details for all schools matching the given filters.
     *
     * Same WHERE logic as get_markers() but returns every column.
     * Used by the /schools REST endpoint when fewer than 1500 results exist,
     * so the frontend can pre-populate popups without individual requests.
     *
     * @param array $filters Same filter array as get_markers().
     * @return array
     */
    public static function get_schools($filters = array())
    {
        global $wpdb;
        $table = self::table_name();

        $where_clauses = array('latitude IS NOT NULL', 'longitude IS NOT NULL');
        $values        = array();

        if (!empty($filters['types']) && $filters['types'] !== 'all') {
            $types           = array_map('trim', explode(',', $filters['types']));
            $ph              = implode(',', array_fill(0, count($types), '%s'));
            $where_clauses[] = "type_etablissement IN ({$ph})";
            $values          = array_merge($values, $types);
        }

        if (!empty($filters['departement']) && $filters['departement'] !== 'all') {
            $where_clauses[] = 'libelle_departement = %s';
            $values[]        = $filters['departement'];
        } elseif (!empty($filters['academie']) && $filters['academie'] !== 'all') {
            $acad_depts = FSM_Academies::get_departments($filters['academie']);
            if (!empty($acad_depts)) {
                $ph              = implode(',', array_fill(0, count($acad_depts), '%s'));
                $where_clauses[] = "libelle_departement IN ({$ph})";
                $values          = array_merge($values, $acad_depts);
            }
        }

        if (!empty($filters['statut']) && $filters['statut'] !== 'all') {
            $where_clauses[] = 'statut_public_prive = %s';
            $values[]        = $filters['statut'];
        }

        if (!empty($filters['ep']) && $filters['ep'] !== 'all') {
            $where_clauses[] = 'education_prioritaire = %s';
            $values[]        = $filters['ep'];
        }

        if (!empty($filters['search'])) {
            $like            = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_clauses[] = '(nom_etablissement LIKE %s OR nom_commune LIKE %s)';
            $values[]        = $like;
            $values[]        = $like;
        }

        $where = implode(' AND ', $where_clauses);
        $sql   = "SELECT * FROM {$table} WHERE {$where} ORDER BY nom_etablissement";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return $wpdb->get_results($sql, ARRAY_A) ?: array();
    }

    /**
     * Get distinct departments from the database.
     *
     * @return array
     */
    public static function get_departments()
    {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_col("SELECT DISTINCT libelle_departement FROM {$table} ORDER BY libelle_departement");
    }

    /**
     * Get statistics.
     *
     * @return array
     */
    public static function get_stats()
    {
        global $wpdb;
        $table = self::table_name();

        $total      = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $with_geo   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE latitude IS NOT NULL");
        $by_type    = $wpdb->get_results("SELECT type_etablissement, COUNT(*) as cnt FROM {$table} GROUP BY type_etablissement ORDER BY cnt DESC", ARRAY_A);
        $by_statut  = $wpdb->get_results("SELECT statut_public_prive, COUNT(*) as cnt FROM {$table} GROUP BY statut_public_prive", ARRAY_A);

        return array(
            'total'        => $total,
            'with_geo'     => $with_geo,
            'by_type'      => $by_type,
            'by_statut'    => $by_statut,
        );
    }

    // ──────────────────────────────────────────────────────────────────
    // Cache management
    // ──────────────────────────────────────────────────────────────────

    /**
     * Clear all marker and department transient caches.
     */
    public static function clear_marker_caches()
    {
        global $wpdb;

        // Delete all fsm_markers_* transients.
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fsm_markers_%' OR option_name LIKE '_transient_timeout_fsm_markers_%'"
        );

        // Also clear department cache.
        delete_transient('fsm_departments');
    }

    // ──────────────────────────────────────────────────────────────────
    // Cron
    // ──────────────────────────────────────────────────────────────────

    public static function schedule_sync()
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'fsm_monthly', self::CRON_HOOK);
        }
    }

    public static function unschedule_sync()
    {
        $ts = wp_next_scheduled(self::CRON_HOOK);
        if ($ts) {
            wp_unschedule_event($ts, self::CRON_HOOK);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Status
    // ──────────────────────────────────────────────────────────────────

    public static function get_status()
    {
        return array(
            'status'       => get_option(self::OPTION_STATUS, 'idle'),
            'last_sync'    => get_option(self::OPTION_LAST_SYNC, false),
            'record_count' => (int) get_option(self::OPTION_COUNT, 0),
            'error'        => get_option(self::OPTION_ERROR, ''),
        );
    }

    // ──────────────────────────────────────────────────────────────────
    // Logging
    // ──────────────────────────────────────────────────────────────────

    private static function log($message)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[French Schools Map] ' . $message);
        }
    }
}
