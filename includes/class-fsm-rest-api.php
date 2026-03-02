<?php

/**
 * REST API endpoints for the French Schools Map.
 *
 * @package French_Schools_Map
 */

if (!defined('ABSPATH')) {
    exit;
}

class FSM_REST_API
{
    const REST_NAMESPACE = 'fsm/v1';

    /**
     * Register all REST routes.
     */
    public static function register_routes()
    {
        // GET /fsm/v1/markers – compact marker list.
        register_rest_route(self::REST_NAMESPACE, '/markers', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_markers'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'types' => array(
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => 'all',
                ),
                'departement' => array(
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => 'all',
                ),
                'academie' => array(
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => 'all',
                ),
                'statut' => array(
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => 'all',
                ),
                'ep' => array(
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => 'all',
                ),
                'search' => array(
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => '',
                ),
                'circonscription' => array(
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => 'all',
                ),
            ),
        ));

        // GET /fsm/v1/school/<id> – full details.
        register_rest_route(self::REST_NAMESPACE, '/school/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_school'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'id' => array(
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        // GET /fsm/v1/academies – académie → départements mapping.
        register_rest_route(self::REST_NAMESPACE, '/academies', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_academies'),
            'permission_callback' => '__return_true',
        ));

        // GET /fsm/v1/schools – full school details for all markers matching filters.
        // Only intended to be called when the result set is small (< 1 500 schools).
        register_rest_route(self::REST_NAMESPACE, '/schools', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_schools'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'types'       => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'all'),
                'departement' => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'all'),
                'academie'    => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'all'),
                'statut'      => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'all'),
                'ep'          => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'all'),
                'search'      => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => ''),
                'circonscription' => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'all'),
            ),
        ));

        // GET /fsm/v1/departments – list of departments.
        register_rest_route(self::REST_NAMESPACE, '/departments', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_departments'),
            'permission_callback' => '__return_true',
        ));

        // GET /fsm/v1/circonscriptions – list of circonscriptions for a département.
        register_rest_route(self::REST_NAMESPACE, '/circonscriptions', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_circonscriptions'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'departement' => array(
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        // GET /fsm/v1/stats – global stats.
        register_rest_route(self::REST_NAMESPACE, '/stats', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_stats'),
            'permission_callback' => '__return_true',
        ));

        // POST /fsm/v1/sync – trigger manual sync (admin only).
        register_rest_route(self::REST_NAMESPACE, '/sync', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'trigger_sync'),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ));

        // GET /fsm/v1/sync-status – sync status (admin only).
        register_rest_route(self::REST_NAMESPACE, '/sync-status', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_sync_status'),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ));
    }

    // ──────────────────────────────────────────────────────────────────
    // Callbacks
    // ──────────────────────────────────────────────────────────────────

    /**
     * Return compact marker data.
     * Response is a JSON array of arrays: [lat, lng, type_id, id, name, city, statut]
     */
    public static function get_markers(WP_REST_Request $request)
    {
        $filters = array(
            'types'       => $request->get_param('types'),
            'departement' => $request->get_param('departement'),
            'academie'    => $request->get_param('academie'),
            'statut'      => $request->get_param('statut'),
            'ep'          => $request->get_param('ep'),
            'search'      => $request->get_param('search'),
            'circonscription' => $request->get_param('circonscription'),
        );

        // Build a cache key from the filters.
        $cache_key = 'fsm_markers_' . md5(wp_json_encode($filters));
        $cached    = get_transient($cache_key);

        if ($cached !== false) {
            return new WP_REST_Response($cached, 200, array(
                'Cache-Control' => 'public, max-age=3600',
            ));
        }

        $markers = FSM_Local_DB::get_markers($filters);

        // Only cache non-empty results (avoid caching before first sync).
        if (!empty($markers)) {
            set_transient($cache_key, $markers, HOUR_IN_SECONDS);
        }

        return new WP_REST_Response($markers, 200, array(
            'Cache-Control' => 'public, max-age=3600',
        ));
    }

    /**
     * Return full school details for all markers matching the current filters.
     * Called only when the client determines the result set is < 1 500 schools.
     */
    public static function get_schools(WP_REST_Request $request)
    {
        $filters = array(
            'types'       => $request->get_param('types'),
            'departement' => $request->get_param('departement'),
            'academie'    => $request->get_param('academie'),
            'statut'      => $request->get_param('statut'),
            'ep'          => $request->get_param('ep'),
            'search'      => $request->get_param('search'),
            'circonscription' => $request->get_param('circonscription'),
        );

        $cache_key = 'fsm_schools_' . md5(wp_json_encode($filters));
        $cached    = get_transient($cache_key);

        if ($cached !== false) {
            return new WP_REST_Response($cached, 200, array(
                'Cache-Control' => 'public, max-age=3600',
            ));
        }

        $schools = FSM_Local_DB::get_schools($filters);

        if (!empty($schools)) {
            set_transient($cache_key, $schools, HOUR_IN_SECONDS);
        }

        return new WP_REST_Response($schools, 200, array(
            'Cache-Control' => 'public, max-age=3600',
        ));
    }

    /**
     * Return full school details.
     */
    public static function get_school(WP_REST_Request $request)
    {
        $id     = (int) $request->get_param('id');
        $school = FSM_Local_DB::get_school($id);

        if (!$school) {
            return new WP_Error('not_found', __('School not found.', 'french-schools-map'), array('status' => 404));
        }

        return new WP_REST_Response($school, 200);
    }

    /**
     * Return académies mapping.
     */
    public static function get_academies(WP_REST_Request $request)
    {
        return new WP_REST_Response(FSM_Academies::get_map(), 200);
    }

    /**
     * Return department list.
     */
    public static function get_departments(WP_REST_Request $request)
    {
        $cached = get_transient('fsm_departments');
        if ($cached !== false) {
            return new WP_REST_Response($cached, 200);
        }

        $depts = FSM_Local_DB::get_departments();
        set_transient('fsm_departments', $depts, DAY_IN_SECONDS);

        return new WP_REST_Response($depts, 200);
    }

    /**
     * Return circonscription list for a given département.
     */
    public static function get_circonscriptions(WP_REST_Request $request)
    {
        $dept = $request->get_param('departement');
        if (empty($dept)) {
            return new WP_REST_Response(array(), 200);
        }

        $cache_key = 'fsm_circo_' . md5($dept);
        $cached    = get_transient($cache_key);
        if ($cached !== false) {
            return new WP_REST_Response($cached, 200);
        }

        $circos = FSM_Local_DB::get_circonscriptions($dept);
        set_transient($cache_key, $circos, DAY_IN_SECONDS);

        return new WP_REST_Response($circos, 200);
    }

    /**
     * Return global statistics.
     */
    public static function get_stats(WP_REST_Request $request)
    {
        $stats = FSM_Local_DB::get_stats();
        return new WP_REST_Response($stats, 200);
    }

    /**
     * Trigger a manual sync (admin only).
     */
    public static function trigger_sync(WP_REST_Request $request)
    {
        $result = FSM_Local_DB::sync();

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(array(
            'success' => true,
            'status'  => FSM_Local_DB::get_status(),
        ), 200);
    }

    /**
     * Return current sync status (admin only).
     */
    public static function get_sync_status(WP_REST_Request $request)
    {
        return new WP_REST_Response(FSM_Local_DB::get_status(), 200);
    }
}
