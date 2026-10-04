<?php

namespace RestApiToolkit\WooCommerce\Controllers;

use RestApiToolkit\REST\ApiRequest;
use RestApiToolkit\REST\ApiResponse;
use RestApiToolkit\REST\Exceptions\ApiException;
use RestApiToolkit\REST\Exceptions\NotFoundException;
use RestApiToolkit\WooCommerce\Transformers\ProductResource;

/**
 * Variaciones de productos variables: /products/{product}/variations
 */
class VariationsController
{
    public function index(ApiRequest $request): \WP_REST_Response
    {
        $parent     = $this->findParent($request);
        $variations = array_filter(array_map('wc_get_product', $parent->get_children()));

        return ApiResponse::success(array_map([ProductResource::class, 'make'], array_values($variations)));
    }

    public function show(ApiRequest $request): array
    {
        return ProductResource::make($this->findVariation($request));
    }

    public function store(ApiRequest $request): \WP_REST_Response
    {
        $parent = $this->findParent($request);
        $v      = $request->validated();

        $variation = new \WC_Product_Variation();
        $variation->set_parent_id($parent->get_id());

        try {
            $this->fill($variation, $v);
            $variation->save();
        } catch (\WC_Data_Exception $e) {
            throw new ApiException($e->getMessage(), 'variation_create_failed', 422);
        }

        return ApiResponse::created(ProductResource::make(wc_get_product($variation->get_id())));
    }

    public function update(ApiRequest $request): array
    {
        $variation = $this->findVariation($request);
        $v         = $request->validated();

        try {
            $this->fill($variation, $v);
            $variation->save();
        } catch (\WC_Data_Exception $e) {
            throw new ApiException($e->getMessage(), 'variation_update_failed', 422);
        }

        return ProductResource::make(wc_get_product($variation->get_id()));
    }

    public function destroy(ApiRequest $request): \WP_REST_Response
    {
        $variation = $this->findVariation($request);
        $variation->delete(true);

        return ApiResponse::success(['deleted' => true, 'id' => (int) $request->route('id')]);
    }

    private function findParent(ApiRequest $request): \WC_Product
    {
        $parent = wc_get_product((int) $request->route('product'));

        if (!$parent || !$parent->is_type('variable')) {
            throw new NotFoundException(__('Producto variable no encontrado.', 'rest-api-toolkit'), 'product_not_found');
        }

        return $parent;
    }

    private function findVariation(ApiRequest $request): \WC_Product_Variation
    {
        $parent    = $this->findParent($request);
        $variation = wc_get_product((int) $request->route('id'));

        if (!$variation instanceof \WC_Product_Variation || $variation->get_parent_id() !== $parent->get_id()) {
            throw new NotFoundException(__('Variación no encontrada.', 'rest-api-toolkit'), 'variation_not_found');
        }

        return $variation;
    }

    /** @throws \WC_Data_Exception */
    private function fill(\WC_Product_Variation $variation, array $v): void
    {
        if (isset($v['attributes']) && is_array($v['attributes'])) {
            $attributes = [];
            foreach ($v['attributes'] as $name => $value) {
                $attributes[sanitize_title((string) $name)] = sanitize_text_field((string) $value);
            }
            $variation->set_attributes($attributes);
        }

        foreach (['regular_price' => 'set_regular_price', 'sale_price' => 'set_sale_price', 'sku' => 'set_sku', 'manage_stock' => 'set_manage_stock', 'stock_quantity' => 'set_stock_quantity', 'stock_status' => 'set_stock_status'] as $field => $setter) {
            if (array_key_exists($field, $v)) {
                $variation->{$setter}($v[$field]);
            }
        }
    }
}
