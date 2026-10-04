<?php

namespace RestApiToolkit\WooCommerce;

use RestApiToolkit\REST\Router;
use RestApiToolkit\WooCommerce\Controllers\CustomersController;
use RestApiToolkit\WooCommerce\Controllers\OrdersController;
use RestApiToolkit\WooCommerce\Controllers\ProductsController;
use RestApiToolkit\WooCommerce\Controllers\VariationsController;
use RestApiToolkit\WordPress\WordPressServiceProvider;

/**
 * Módulo WooCommerce desacoplado. Si WooCommerce no está activo, no registra nada.
 */
class WooCommerceServiceProvider
{
    public static function isActive(): bool
    {
        return class_exists('WooCommerce');
    }

    public static function register(Router $router): void
    {
        if (!self::isActive()) {
            return;
        }

        // ── Products ────────────────────────────────────────────────────
        $router->get('/products', ProductsController::class . '@index', [
            'public'  => true,
            'summary' => 'Listar productos',
            'schema'  => WordPressServiceProvider::listSchema([
                'status'         => ['type' => 'string', 'enum' => ['publish', 'draft', 'pending', 'private']],
                'sku'            => ['type' => 'string', 'max_length' => 100],
                'category'       => ['type' => 'string', 'max_length' => 100],
                'stock_status'   => ['type' => 'string', 'enum' => ['instock', 'outofstock', 'onbackorder']],
            ]),
        ]);
        $router->get('/products/{id}', ProductsController::class . '@show', ['public' => true, 'summary' => 'Obtener producto']);
        $router->post('/products', ProductsController::class . '@store', [
            'permission' => 'products.create',
            'summary'    => 'Crear producto',
            'schema'     => self::productSchema(true),
        ]);
        $router->put('/products/{id}', ProductsController::class . '@update', [
            'permission' => 'products.update',
            'summary'    => 'Actualizar producto',
            'schema'     => self::productSchema(false),
        ]);
        $router->delete('/products/{id}', ProductsController::class . '@destroy', [
            'permission' => 'products.delete',
            'summary'    => 'Eliminar producto',
        ]);

        // ── Variations ─────────────────────────────────────────────────
        $router->get('/products/{product}/variations', VariationsController::class . '@index', [
            'public'  => true,
            'summary' => 'Listar variaciones de un producto',
        ]);
        $router->get('/products/{product}/variations/{id}', VariationsController::class . '@show', [
            'public'  => true,
            'summary' => 'Obtener variación',
        ]);
        $router->post('/products/{product}/variations', VariationsController::class . '@store', [
            'permission' => 'products.create',
            'summary'    => 'Crear variación',
            'schema'     => self::variationSchema(),
        ]);
        $router->put('/products/{product}/variations/{id}', VariationsController::class . '@update', [
            'permission' => 'products.update',
            'summary'    => 'Actualizar variación',
            'schema'     => self::variationSchema(),
        ]);
        $router->delete('/products/{product}/variations/{id}', VariationsController::class . '@destroy', [
            'permission' => 'products.delete',
            'summary'    => 'Eliminar variación',
        ]);

        // ── Orders ─────────────────────────────────────────────────────
        $router->get('/orders', OrdersController::class . '@index', [
            'permission' => 'orders.read',
            'summary'    => 'Listar pedidos',
            'schema'     => WordPressServiceProvider::listSchema([
                'status'      => ['type' => 'string', 'max_length' => 30, 'sanitize' => 'key'],
                'customer_id' => ['type' => 'integer', 'min' => 1],
            ]),
        ]);
        $router->get('/orders/{id}', OrdersController::class . '@show', [
            'permission' => 'orders.read',
            'summary'    => 'Obtener pedido',
        ]);
        $router->post('/orders', OrdersController::class . '@store', [
            'permission' => 'orders.create',
            'summary'    => 'Crear pedido',
            'schema'     => [
                'customer_id'    => ['type' => 'integer', 'min' => 0, 'default' => 0],
                'items'          => ['required' => true, 'type' => 'array'],
                'billing'        => ['type' => 'array'],
                'shipping'       => ['type' => 'array'],
                'status'         => ['type' => 'string', 'sanitize' => 'key', 'default' => 'pending'],
                'customer_note'  => ['type' => 'string', 'max_length' => 5000],
                'payment_method' => ['type' => 'string', 'sanitize' => 'key'],
            ],
        ]);
        $router->put('/orders/{id}', OrdersController::class . '@update', [
            'permission' => 'orders.update',
            'summary'    => 'Actualizar pedido (estado, direcciones, nota)',
            'schema'     => [
                'status'   => ['type' => 'string', 'sanitize' => 'key'],
                'billing'  => ['type' => 'array'],
                'shipping' => ['type' => 'array'],
                'note'     => ['type' => 'string', 'max_length' => 5000],
            ],
        ]);
        $router->delete('/orders/{id}', OrdersController::class . '@destroy', [
            'permission' => 'orders.delete',
            'summary'    => 'Eliminar pedido',
        ]);

        // ── Customers ──────────────────────────────────────────────────
        $router->get('/customers', CustomersController::class . '@index', [
            'permission' => 'customers.read',
            'summary'    => 'Listar clientes',
            'schema'     => WordPressServiceProvider::listSchema(),
        ]);
        $router->get('/customers/{id}', CustomersController::class . '@show', [
            'permission' => 'customers.read',
            'summary'    => 'Obtener cliente',
        ]);
        $router->post('/customers', CustomersController::class . '@store', [
            'permission' => 'customers.create',
            'summary'    => 'Crear cliente',
            'schema'     => self::customerSchema(true),
        ]);
        $router->put('/customers/{id}', CustomersController::class . '@update', [
            'permission' => 'customers.update',
            'summary'    => 'Actualizar cliente',
            'schema'     => self::customerSchema(false),
        ]);
        $router->delete('/customers/{id}', CustomersController::class . '@destroy', [
            'permission' => 'customers.delete',
            'summary'    => 'Eliminar cliente',
        ]);
    }

    private static function productSchema(bool $isCreate): array
    {
        return [
            'name'              => ['required' => $isCreate, 'type' => 'string', 'max_length' => 200],
            'type'              => ['type' => 'string', 'enum' => ['simple', 'variable', 'grouped', 'external']] + ($isCreate ? ['default' => 'simple'] : []),
            'status'            => ['type' => 'string', 'enum' => ['publish', 'draft', 'pending', 'private']] + ($isCreate ? ['default' => 'draft'] : []),
            'description'       => ['type' => 'string', 'sanitize' => 'html'],
            'short_description' => ['type' => 'string', 'sanitize' => 'html', 'max_length' => 5000],
            'sku'               => ['type' => 'string', 'max_length' => 100],
            'regular_price'     => ['type' => 'float', 'min' => 0],
            'sale_price'        => ['type' => 'float', 'min' => 0],
            'manage_stock'      => ['type' => 'boolean'],
            'stock_quantity'    => ['type' => 'integer', 'min' => 0],
            'stock_status'      => ['type' => 'string', 'enum' => ['instock', 'outofstock', 'onbackorder']],
            'weight'            => ['type' => 'float', 'min' => 0],
            'categories'        => ['type' => 'array', 'items_type' => 'integer'],
            'tags'              => ['type' => 'array', 'items_type' => 'integer'],
            'image_id'          => ['type' => 'integer', 'min' => 1],
            'gallery_ids'       => ['type' => 'array', 'items_type' => 'integer'],
        ];
    }

    private static function variationSchema(): array
    {
        return [
            'attributes'     => ['type' => 'array'],
            'regular_price'  => ['type' => 'float', 'min' => 0],
            'sale_price'     => ['type' => 'float', 'min' => 0],
            'sku'            => ['type' => 'string', 'max_length' => 100],
            'manage_stock'   => ['type' => 'boolean'],
            'stock_quantity' => ['type' => 'integer', 'min' => 0],
            'stock_status'   => ['type' => 'string', 'enum' => ['instock', 'outofstock', 'onbackorder']],
        ];
    }

    private static function customerSchema(bool $isCreate): array
    {
        return [
            'email'      => ['required' => $isCreate, 'type' => 'email'],
            'username'   => ['type' => 'string', 'min_length' => 3, 'max_length' => 60, 'regex' => '/^[a-zA-Z0-9._\-]+$/'],
            'password'   => ['type' => 'string', 'min_length' => 12, 'max_length' => 128, 'sanitize' => 'none'],
            'first_name' => ['type' => 'string', 'max_length' => 100],
            'last_name'  => ['type' => 'string', 'max_length' => 100],
            'billing'    => ['type' => 'array'],
            'shipping'   => ['type' => 'array'],
        ];
    }
}
