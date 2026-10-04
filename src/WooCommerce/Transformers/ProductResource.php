<?php

namespace RestApiToolkit\WooCommerce\Transformers;

class ProductResource
{
    public static function make(\WC_Product $product): array
    {
        $data = [
            'id'                => $product->get_id(),
            'name'              => $product->get_name(),
            'slug'              => $product->get_slug(),
            'type'              => $product->get_type(),
            'status'            => $product->get_status(),
            'sku'               => $product->get_sku(),
            'price'             => $product->get_price() !== '' ? (float) $product->get_price() : null,
            'regular_price'     => $product->get_regular_price() !== '' ? (float) $product->get_regular_price() : null,
            'sale_price'        => $product->get_sale_price() !== '' ? (float) $product->get_sale_price() : null,
            'on_sale'           => $product->is_on_sale(),
            'description'       => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'manage_stock'      => $product->managing_stock(),
            'stock_quantity'    => $product->get_stock_quantity(),
            'stock_status'      => $product->get_stock_status(),
            'weight'            => $product->get_weight(),
            'categories'        => array_map('intval', $product->get_category_ids()),
            'tags'              => array_map('intval', $product->get_tag_ids()),
            'image'             => wp_get_attachment_url($product->get_image_id()) ?: null,
            'gallery'           => array_values(array_filter(array_map('wp_get_attachment_url', $product->get_gallery_image_ids()))),
            'permalink'         => $product->get_permalink(),
            'variations'        => $product->is_type('variable') ? array_map('intval', $product->get_children()) : [],
            'date_created'      => $product->get_date_created() ? $product->get_date_created()->format('c') : null,
            'date_modified'     => $product->get_date_modified() ? $product->get_date_modified()->format('c') : null,
        ];

        if ($product->is_type('variation')) {
            $data['parent_id']  = $product->get_parent_id();
            $data['attributes'] = $product->get_attributes();
        }

        return apply_filters('rat_product_response', $data, $product);
    }
}
