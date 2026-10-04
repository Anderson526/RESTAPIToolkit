<?php

namespace RestApiToolkit\Database;

/**
 * Migraciones de las tablas propias del plugin.
 */
class Migrations
{
    public const DB_VERSION = '1.0.0';
    public const OPTION     = 'rat_db_version';

    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $keys    = $wpdb->prefix . 'rat_api_keys';
        $logs    = $wpdb->prefix . 'rat_api_logs';

        dbDelta("CREATE TABLE {$keys} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            key_id VARCHAR(64) NOT NULL,
            name VARCHAR(191) NOT NULL DEFAULT '',
            secret_hash VARCHAR(128) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            scopes LONGTEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            last_used_at DATETIME NULL DEFAULT NULL,
            expires_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY key_id (key_id),
            KEY user_id (user_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$logs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id VARCHAR(40) NOT NULL DEFAULT '',
            method VARCHAR(10) NOT NULL DEFAULT '',
            route VARCHAR(191) NOT NULL DEFAULT '',
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            api_key_id BIGINT UNSIGNED NULL DEFAULT NULL,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            status_code SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            duration FLOAT NOT NULL DEFAULT 0,
            user_agent VARCHAR(191) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY route (route),
            KEY status_code (status_code),
            KEY created_at (created_at)
        ) {$charset};");

        update_option(self::OPTION, self::DB_VERSION);
    }

    /**
     * Reejecuta las migraciones si la versión de esquema cambió.
     */
    public static function maybeUpgrade(): void
    {
        if (get_option(self::OPTION) !== self::DB_VERSION) {
            self::install();
        }
    }
}
