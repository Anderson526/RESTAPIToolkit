<?php

namespace RestApiToolkit\Core;

use RestApiToolkit\Admin\AdminMenu;
use RestApiToolkit\Database\Migrations;
use RestApiToolkit\Logging\Logger;
use RestApiToolkit\REST\Router;
use RestApiToolkit\Security\Cors;
use RestApiToolkit\WooCommerce\WooCommerceServiceProvider;
use RestApiToolkit\WordPress\WordPressServiceProvider;

/**
 * Punto de arranque del plugin. Registra servicios, rutas y hooks.
 */
final class Plugin
{
    /** @var Plugin|null */
    private static $instance;

    /** @var Container */
    private $container;

    public static function boot(): void
    {
        if (self::$instance instanceof self) {
            return;
        }
        self::$instance = new self();
        self::$instance->run();
    }

    public static function container(): Container
    {
        return self::$instance->container;
    }

    private function __construct()
    {
        $this->container = new Container();
    }

    private function run(): void
    {
        Migrations::maybeUpgrade();

        $container = $this->container;
        $container->singleton(Container::class, static function () use ($container) {
            return $container;
        });

        $router = new Router($container, RAT_REST_NAMESPACE);
        $container->singleton(Router::class, static function () use ($router) {
            return $router;
        });

        // Módulos de recursos.
        WordPressServiceProvider::register($router);
        WooCommerceServiceProvider::register($router);

        /**
         * Permite a otros plugins registrar endpoints sobre el toolkit.
         *
         * @param Router    $router
         * @param Container $container
         */
        do_action('rat_register_routes', $router, $container);

        add_action('rest_api_init', [$router, 'register']);

        (new Cors())->register();

        add_action('rat_daily_cleanup', [Logger::class, 'cleanup']);

        if (is_admin()) {
            (new AdminMenu($container))->register();
        }
    }
}
