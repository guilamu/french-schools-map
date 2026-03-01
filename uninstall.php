<?php

/**
 * Uninstall French Schools Map.
 *
 * Fired when the plugin is uninstalled via the WordPress admin.
 *
 * @package French_Schools_Map
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Include the local DB class to access its methods.
require_once plugin_dir_path(__FILE__) . 'includes/class-fsm-local-db.php';

// Drop the schools table and remove all options.
FSM_Local_DB::drop_table();

// Clear all marker caches.
FSM_Local_DB::clear_marker_caches();

// Also clean up staging / old tables if they still exist.
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}fsm_schools_staging");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}fsm_schools_old");

// Delete all plugin settings (drop_table already removes sync options).
delete_option('fsm_default_departement');
delete_option('fsm_default_academie');
delete_option('fsm_default_types');
delete_option('fsm_default_statut');
delete_option('fsm_default_ep');

// Unschedule cron.
$timestamp = wp_next_scheduled('fsm_monthly_sync');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'fsm_monthly_sync');
}
