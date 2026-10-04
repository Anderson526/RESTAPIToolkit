<?php

namespace RestApiToolkit\WooCommerce\Transformers;

class CustomerResource
{
    public static function make(\WC_Customer $customer): array
    {
        $data = [
            'id'           => $customer->get_id(),
            'email'        => $customer->get_email(),
            'username'     => $customer->get_username(),
            'first_name'   => $customer->get_first_name(),
            'last_name'    => $customer->get_last_name(),
            'billing'      => [
                'first_name' => $customer->get_billing_first_name(),
                'last_name'  => $customer->get_billing_last_name(),
                'company'    => $customer->get_billing_company(),
                'address_1'  => $customer->get_billing_address_1(),
                'address_2'  => $customer->get_billing_address_2(),
                'city'       => $customer->get_billing_city(),
                'state'      => $customer->get_billing_state(),
                'postcode'   => $customer->get_billing_postcode(),
                'country'    => $customer->get_billing_country(),
                'phone'      => $customer->get_billing_phone(),
            ],
            'shipping'     => [
                'first_name' => $customer->get_shipping_first_name(),
                'last_name'  => $customer->get_shipping_last_name(),
                'address_1'  => $customer->get_shipping_address_1(),
                'address_2'  => $customer->get_shipping_address_2(),
                'city'       => $customer->get_shipping_city(),
                'state'      => $customer->get_shipping_state(),
                'postcode'   => $customer->get_shipping_postcode(),
                'country'    => $customer->get_shipping_country(),
            ],
            'orders_count' => $customer->get_order_count(),
            'total_spent'  => (float) $customer->get_total_spent(),
            'date_created' => $customer->get_date_created() ? $customer->get_date_created()->format('c') : null,
        ];

        return apply_filters('rat_customer_response', $data, $customer);
    }
}
