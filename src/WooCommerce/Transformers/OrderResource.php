<?php

namespace RestApiToolkit\WooCommerce\Transformers;

class OrderResource
{
    public static function make(\WC_Order $order): array
    {
        $items = [];
        foreach ($order->get_items() as $item) {
            /** @var \WC_Order_Item_Product $item */
            $items[] = [
                'id'         => $item->get_id(),
                'product_id' => method_exists($item, 'get_product_id') ? $item->get_product_id() : 0,
                'name'       => $item->get_name(),
                'quantity'   => method_exists($item, 'get_quantity') ? $item->get_quantity() : 1,
                'subtotal'   => method_exists($item, 'get_subtotal') ? (float) $item->get_subtotal() : 0,
                'total'      => (float) $item->get_total(),
            ];
        }

        $data = [
            'id'             => $order->get_id(),
            'number'         => $order->get_order_number(),
            'status'         => $order->get_status(),
            'currency'       => $order->get_currency(),
            'customer_id'    => $order->get_customer_id(),
            'customer_note'  => $order->get_customer_note(),
            'payment_method' => $order->get_payment_method(),
            'subtotal'       => (float) $order->get_subtotal(),
            'total_tax'      => (float) $order->get_total_tax(),
            'shipping_total' => (float) $order->get_shipping_total(),
            'discount_total' => (float) $order->get_discount_total(),
            'total'          => (float) $order->get_total(),
            'billing'        => $order->get_address('billing'),
            'shipping'       => $order->get_address('shipping'),
            'items'          => $items,
            'date_created'   => $order->get_date_created() ? $order->get_date_created()->format('c') : null,
            'date_modified'  => $order->get_date_modified() ? $order->get_date_modified()->format('c') : null,
        ];

        return apply_filters('rat_order_response', $data, $order);
    }
}
