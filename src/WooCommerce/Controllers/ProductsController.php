<?php

namespace RestApiToolkit\WooCommerce\Controllers;

use RestApiToolkit\REST\ApiRequest;
use RestApiToolkit\REST\ApiResponse;
use RestApiToolkit\REST\Exceptions\ApiException;
use RestApiToolkit\REST\Exceptions\NotFoundException;
use RestApiToolkit\WooCommerce\Transformers\ProductResource;

class ProductsController
{
    public function index(ApiRequest $request): \WP_REST_Response
    {
        $v = $request->validated();

        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'paged'          => $v['page'],
            'posts_per_page' => $v['per_page'],
            's'              => $v['search'] ?? '',
            'fields'         => 'ids',
        ];

        if (!empty($v['status']) && current_user_can('manage_woocommerce')) {
            $args['post_status'] = $v['status'];
        }
        if (!empty($v['category'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $v['category'],
            ];
        }

        $metaQuery = [];
        if (!empty($v['sku'])) {
            $metaQuery[] = ['key' => '_sku', 'value' => $v['sku']];
        }
        if (!empty($v['stock_status'])) {
            $metaQuery[] = ['key' => '_stock_status', 'value' => $v['stock_status']];
        }
        if ($metaQuery) {
            $args['meta_query'] = $metaQuery;
        }

        $query    = new \WP_Query($args);
        $products = array_filter(array_map('wc_get_product', $query->posts));

        return ApiResponse::paginated(
            array_map([ProductResource::class, 'make'], $products),
            $v['page'],
            $v['per_page'],
            (int) $query->found_posts
        );
    }

    public function show(ApiRequest $request): array
    {
        return ProductResource::make($this->findProduct($request));
    }

    public function store(ApiRequest $request): \WP_REST_Response
    {
        $v = $request->validated();

        $classMap = [
            'simple'   => \WC_Product_Simple::class,
            'variable' => \WC_Product_Variable::class,
            'grouped'  => \WC_Product_Grouped::class,
            'external' => \WC_Product_External::class,
        ];
        $class = $classMap[$v['type'] ?? 'simple'];

        /** @var \WC_Product $product */
        $product = new $class();

        try {
            $this->fill($product, $v);
            $product->save();
        } catch (\WC_Data_Exception $e) {
            throw new ApiException($e->getMessage(), 'product_create_failed', 422);
        }

        return ApiResponse::created(ProductResource::make(wc_get_product($product->get_id())));
    }

    public function update(ApiRequest $request): array
    {
        $product = $this->findProduct($request, true);
        $v       = $request->validated();

        try {
            $this->fill($product, $v);
            $product->save();
        } catch (\WC_Data_Exception $e) {
            throw new ApiException($e->getMessage(), 'product_update_failed', 422);
        }

        return ProductResource::make(wc_get_product($product->get_id()));
    }

    public function destroy(ApiRequest $request): \WP_REST_Response
    {
        $product = $this->findProduct($request, true);
        $force   = filter_var($request->query('force', false), FILTER_VALIDATE_BOOLEAN);

        $product->delete($force);

        return ApiResponse::success(['deleted' => true, 'id' => (int) $request->route('id'), 'force' => $force]);
    }

    private function findProduct(ApiRequest $request, bool $forEdit = false): \WC_Product
    {
        $product = wc_get_product((int) $request->route('id'));

        if (!$product || $product->is_type('variation')) {
            throw new NotFoundException(__('Producto no encontrado.', 'rest-api-toolkit'), 'product_not_found');
        }

        $visible = $product->get_status() === 'publish' || current_user_can('manage_woocommerce');
        if (!$forEdit && !$visible) {
            throw new NotFoundException(__('Producto no encontrado.', 'rest-api-toolkit'), 'product_not_found');
        }

        return $product;
    }

    /** @throws \WC_Data_Exception */
    private function fill(\WC_Product $product, array $v): void
    {
        $setters = [
            'name'              => 'set_name',
            'status'            => 'set_status',
            'description'       => 'set_description',
            'short_description' => 'set_short_description',
            'sku'               => 'set_sku',
            'regular_price'     => 'set_regular_price',
            'sale_price'        => 'set_sale_price',
            'manage_stock'      => 'set_manage_stock',
            'stock_quantity'    => 'set_stock_quantity',
            'stock_status'      => 'set_stock_status',
            'weight'            => 'set_weight',
            'categories'        => 'set_category_ids',
            'tags'              => 'set_tag_ids',
            'image_id'          => 'set_image_id',
            'gallery_ids'       => 'set_gallery_image_ids',
        ];

        foreach ($setters as $field => $setter) {
            if (array_key_exists($field, $v) && is_callable([$product, $setter])) {
                $product->{$setter}($v[$field]);
            }
        }
    }
}
