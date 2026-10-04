<?php
/**
 * Plugin Name:       REST API Toolkit
 * Plugin URI:        https://example.com/rest-api-toolkit
 * Description:       Capa de infraestructura REST para WordPress y WooCommerce: router propio, autenticación, permisos, validación, rate limiting, logging, API keys, documentación OpenAPI y panel de administración.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Anderson D Chila P
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rest-api-toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

define('RAT_VERSION', '0.1.0');
define('RAT_PLUGIN_FILE', __FILE__);
define('RAT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RAT_REST_NAMESPACE', 'rat/v1');

/**
 * Autoloader PSR-4: RestApiToolkit\ => src/
 */
spl_autoload_register(static function ($class) {
    $prefix = 'RestApiToolkit\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = RAT_PLUGIN_DIR . 'src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($path)) {
        require $path;
    }
});

register_activation_hook(__FILE__, static function () {
    \RestApiToolkit\Database\Migrations::install();
    if (!wp_next_scheduled('rat_daily_cleanup')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'rat_daily_cleanup');
    }
});

register_deactivation_hook(__FILE__, static function () {
    wp_clear_scheduled_hook('rat_daily_cleanup');
});

add_action('plugins_loaded', static function () {
    \RestApiToolkit\Core\Plugin::boot();
});
