<?php

namespace RestApiToolkit\Admin;

use RestApiToolkit\Admin\Pages\ApiKeysPage;
use RestApiToolkit\Admin\Pages\DashboardPage;
use RestApiToolkit\Admin\Pages\DonationsPage;
use RestApiToolkit\Admin\Pages\LogsPage;
use RestApiToolkit\Admin\Pages\SettingsPage;
use RestApiToolkit\Core\Container;

/**
 * Menú de administración: Dashboard, API Keys, Logs, Ajustes y Donaciones.
 */
class AdminMenu
{
    private const CAPABILITY = 'manage_options';

    /** @var Container */
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_init', [SettingsPage::class, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('plugin_row_meta', [$this, 'pluginRowMeta'], 10, 2);
    }

    public function addMenu(): void
    {
        add_menu_page(
            __('REST API Toolkit', 'rest-api-toolkit'),
            __('REST API Toolkit', 'rest-api-toolkit'),
            self::CAPABILITY,
            'rat-dashboard',
            [$this->container->get(DashboardPage::class), 'render'],
            'dashicons-rest-api',
            80
        );

        add_submenu_page('rat-dashboard', __('Dashboard', 'rest-api-toolkit'), __('Dashboard', 'rest-api-toolkit'), self::CAPABILITY, 'rat-dashboard', [$this->container->get(DashboardPage::class), 'render']);
        add_submenu_page('rat-dashboard', __('API Keys', 'rest-api-toolkit'), __('API Keys', 'rest-api-toolkit'), self::CAPABILITY, 'rat-api-keys', [$this->container->get(ApiKeysPage::class), 'render']);
        add_submenu_page('rat-dashboard', __('Logs', 'rest-api-toolkit'), __('Logs', 'rest-api-toolkit'), self::CAPABILITY, 'rat-logs', [$this->container->get(LogsPage::class), 'render']);
        add_submenu_page('rat-dashboard', __('Ajustes', 'rest-api-toolkit'), __('Ajustes', 'rest-api-toolkit'), self::CAPABILITY, 'rat-settings', [$this->container->get(SettingsPage::class), 'render']);
        add_submenu_page('rat-dashboard', __('Donaciones', 'rest-api-toolkit'), __('Donaciones ❤', 'rest-api-toolkit'), self::CAPABILITY, 'rat-donations', [$this->container->get(DonationsPage::class), 'render']);
    }

    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, 'rat-') === false) {
            return;
        }

        wp_enqueue_style(
            'rat-admin',
            RAT_PLUGIN_URL . 'assets/css/admin.css',
            [],
            RAT_VERSION
        );
    }

    /** Enlace de donación en la fila del plugin. */
    public function pluginRowMeta(array $meta, string $file): array
    {
        if ($file === plugin_basename(RAT_PLUGIN_FILE)) {
            $meta[] = '<a href="' . esc_url(admin_url('admin.php?page=rat-donations')) . '">'
                . esc_html__('Donar ❤', 'rest-api-toolkit') . '</a>';
            $meta[] = '<a href="' . esc_url(rest_url(RAT_REST_NAMESPACE . '/openapi.json')) . '" target="_blank" rel="noopener noreferrer">'
                . esc_html__('OpenAPI', 'rest-api-toolkit') . '</a>';
        }

        return $meta;
    }
}
