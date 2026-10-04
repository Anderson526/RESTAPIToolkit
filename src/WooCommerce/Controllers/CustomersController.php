<?php

namespace RestApiToolkit\WooCommerce\Controllers;

use RestApiToolkit\REST\ApiRequest;
use RestApiToolkit\REST\ApiResponse;
use RestApiToolkit\REST\Exceptions\ApiException;
use RestApiToolkit\REST\Exceptions\ConflictException;
use RestApiToolkit\REST\Exceptions\NotFoundException;
use RestApiToolkit\WooCommerce\Transformers\CustomerResource;

class CustomersController
{
    public function index(ApiRequest $request): \WP_REST_Response
    {
        $v = $request->validated();

        $args = [
            'role'   => 'customer',
            'number' => $v['per_page'],
            'paged'  => $v['page'],
        ];
        if (!empty($v['search'])) {
            $args['search'] = '*' . $v['search'] . '*';
        }

        $query = new \WP_User_Query($args);

        $customers = array_map(static function (\WP_User $user) {
            return CustomerResource::make(new \WC_Customer($user->ID));
        }, $query->get_results());

        return ApiResponse::paginated($customers, $v['page'], $v['per_page'], (int) $query->get_total());
    }

    public function show(ApiRequest $request): array
    {
        return CustomerResource::make($this->findCustomer($request));
    }

    public function store(ApiRequest $request): \WP_REST_Response
    {
        $v = $request->validated();

        if (email_exists($v['email'])) {
            throw new ConflictException(__('Ya existe un usuario con ese email.', 'rest-api-toolkit'), 'customer_exists');
        }

        try {
            $customer = new \WC_Customer();
            $customer->set_email($v['email']);
            $customer->set_username($v['username'] ?? sanitize_user(current(explode('@', $v['email'])), true));
            $customer->set_password($v['password'] ?? wp_generate_password(24));
            $this->fill($customer, $v);
            $customer->save();
        } catch (\WC_Data_Exception $e) {
            throw new ApiException($e->getMessage(), 'customer_create_failed', 422);
        }

        return ApiResponse::created(CustomerResource::make(new \WC_Customer($customer->get_id())));
    }

    public function update(ApiRequest $request): array
    {
        $customer = $this->findCustomer($request);
        $v        = $request->validated();

        try {
            if (isset($v['email']) && $v['email'] !== $customer->get_email() && email_exists($v['email'])) {
                throw new ConflictException(__('Ya existe un usuario con ese email.', 'rest-api-toolkit'), 'customer_exists');
            }
            if (isset($v['email'])) {
                $customer->set_email($v['email']);
            }
            if (isset($v['password'])) {
                $customer->set_password($v['password']);
            }
            $this->fill($customer, $v);
            $customer->save();
        } catch (\WC_Data_Exception $e) {
            throw new ApiException($e->getMessage(), 'customer_update_failed', 422);
        }

        return CustomerResource::make(new \WC_Customer($customer->get_id()));
    }

    public function destroy(ApiRequest $request): \WP_REST_Response
    {
        $customer = $this->findCustomer($request);

        require_once ABSPATH . 'wp-admin/includes/user.php';

        if (!wp_delete_user($customer->get_id())) {
            throw new ApiException(__('No se pudo eliminar el cliente.', 'rest-api-toolkit'), 'customer_delete_failed', 500);
        }

        return ApiResponse::success(['deleted' => true, 'id' => $customer->get_id()]);
    }

    private function findCustomer(ApiRequest $request): \WC_Customer
    {
        $id   = (int) $request->route('id');
        $user = get_user_by('id', $id);

        if (!$user) {
            throw new NotFoundException(__('Cliente no encontrado.', 'rest-api-toolkit'), 'customer_not_found');
        }

        return new \WC_Customer($id);
    }

    /** @throws \WC_Data_Exception */
    private function fill(\WC_Customer $customer, array $v): void
    {
        if (isset($v['first_name'])) {
            $customer->set_first_name($v['first_name']);
        }
        if (isset($v['last_name'])) {
            $customer->set_last_name($v['last_name']);
        }

        foreach (['billing', 'shipping'] as $group) {
            if (empty($v[$group]) || !is_array($v[$group])) {
                continue;
            }
            foreach ($v[$group] as $field => $value) {
                $setter = 'set_' . $group . '_' . sanitize_key((string) $field);
                if (is_scalar($value) && is_callable([$customer, $setter])) {
                    $customer->{$setter}(sanitize_text_field((string) $value));
                }
            }
        }
    }
}
