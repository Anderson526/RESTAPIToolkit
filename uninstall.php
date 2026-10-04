<?php
/**
 * Limpieza al desinstalar REST API Toolkit.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$tables = [
    $wpdb->prefix . 'rat_api_keys',
    $wpdb->prefix . 'rat_api_logs',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB
}

delete_option('rat_settings');
delete_option('rat_donations');
delete_option('rat_db_version');

wp_clear_scheduled_hook('rat_daily_cleanup');
