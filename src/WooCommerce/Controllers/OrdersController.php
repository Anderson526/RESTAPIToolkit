<?php

namespace RestApiToolkit\WooCommerce\Controllers;

use RestApiToolkit\REST\ApiRequest;
use RestApiToolkit\REST\ApiResponse;
use RestApiToolkit\REST\Exceptions\ApiException;
use RestApiToolkit\REST\Exceptions\NotFoundException;
use RestApiToolkit\REST\Exceptions\ValidationException;
use RestApiToolkit\WooCommerce\Transformers\OrderResource;

class OrdersController
{
    public function index(ApiRequest $request): \WP_REST_Response
    {
        $v = $request->validated();

        $args = [
            'limit'    => $v['per_page'],
            'page'     => $v['page'],
            'paginate' => true,
            'type'     => 'shop_order',
        ];
        if (!empty($v['status'])) {
            $args['status'] = $v['status'];
        }
        if (!empty($v['customer_id'])) {
            $args['customer_id'] = $v['customer_id'];
        }

        $results = wc_get_orders($args);

        $orders = array_filter($results->orders, static function ($order) {
            return $order instanceof \WC_Order;
        });

        return ApiResponse::paginated(
            array_map([OrderResource::class, 'make'], array_values($orders)),
            $v['page'],
            $v['per_page'],
            (int) $results->total
        );
    }

    public function show(ApiRequest $request): array
    {
        return OrderResource::make($this->findOrder($request));
    }

    public function store(ApiRequest $request): \WP_REST_Response
    {
        $v = $request->validated();

        $items = array_values((array) $v['items']);
        if (!$items) {
            throw new ValidationException(['items' => [__('El pedido necesita al menos un producto.', 'rest-api-toolkit')]]);
        }

        $order = wc_create_order(['customer_id' => (int) ($v['customer_id'] ?? 0)]);
        if (is_wp_error($order)) {
            throw new ApiException($order->get_error_message(), 'order_create_failed', 422);
        }

        try {
            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $quantity  = max(1, (int) ($item['quantity'] ?? 1));
                $product   = wc_get_product($productId);

                if (!$product) {
                    throw new NotFoundException(
                        sprintf(__('Producto %d no encontrado.', 'rest-api-toolkit'), $productId),
                        'product_not_found'
                    );
                }
                $order->add_product($product, $quantity);
            }

            if (!empty($v['billing']) && is_array($v['billing'])) {
                $order->set_address($this->sanitizeAddress($v['billing']), 'billing');
            }
            if (!empty($v['shipping']) && is_array($v['shipping'])) {
                $order->set_address($this->sanitizeAddress($v['shipping']), 'shipping');
            }
            if (!empty($v['customer_note'])) {
                $order->set_customer_note($v['customer_note']);
            }
            if (!empty($v['payment_method'])) {
                $order->set_payment_method($v['payment_method']);
            }

            $order->calculate_totals();

            if (!empty($v['status']) && $v['status'] !== 'pending') {
                $order->update_status($v['status'], __('Estado inicial vía REST API Toolkit.', 'rest-api-toolkit'));
            }

            $order->save();
        } catch (NotFoundException $e) {
            $order->delete(true); // limpieza del pedido parcial
            throw $e;
        } catch (\WC_Data_Exception $e) {
            $order->delete(true);
            throw new ApiException($e->getMessage(), 'order_create_failed', 422);
        }

        return ApiResponse::created(OrderResource::make(wc_get_order($order->get_id())));
    }

    public function update(ApiRequest $request): array
    {
        $order = $this->findOrder($request);
        $v     = $request->validated();

        try {
            if (!empty($v['billing']) && is_array($v['billing'])) {
                $order->set_address($this->sanitizeAddress($v['billing']), 'billing');
            }
            if (!empty($v['shipping']) && is_array($v['shipping'])) {
                $order->set_address($this->sanitizeAddress($v['shipping']), 'shipping');
            }
            if (!empty($v['note'])) {
                $order->add_order_note($v['note'], false, true);
            }
            if (!empty($v['status'])) {
                $order->update_status($v['status'], __('Actualizado vía REST API Toolkit.', 'rest-api-toolkit'));
            }

            $order->save();
        } catch (\WC_Data_Exception $e) {
            throw new ApiException($e->getMessage(), 'order_update_failed', 422);
        }

        return OrderResource::make(wc_get_order($order->get_id()));
    }

    public function destroy(ApiRequest $request): \WP_REST_Response
    {
        $order = $this->findOrder($request);
        $force = filter_var($request->query('force', false), FILTER_VALIDATE_BOOLEAN);

        $order->delete($force);

        return ApiResponse::success(['deleted' => true, 'id' => (int) $request->route('id'), 'force' => $force]);
    }

    private function findOrder(ApiRequest $request): \WC_Order
    {
        $order = wc_get_order((int) $request->route('id'));

        if (!$order instanceof \WC_Order) {
            throw new NotFoundException(__('Pedido no encontrado.', 'rest-api-toolkit'), 'order_not_found');
        }

        return $order;
    }

    private function sanitizeAddress(array $address): array
    {
        $allowed = ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'email', 'phone'];
        $clean   = [];

        foreach ($allowed as $field) {
            if (isset($address[$field]) && is_scalar($address[$field])) {
                $clean[$field] = $field === 'email'
                    ? sanitize_email((string) $address[$field])
                    : sanitize_text_field((string) $address[$field]);
            }
        }

        return $clean;
    }
}
