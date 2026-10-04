<?php

namespace RestApiToolkit\WordPress\Controllers;

use RestApiToolkit\REST\ApiRequest;
use RestApiToolkit\REST\ApiResponse;
use RestApiToolkit\REST\Exceptions\ApiException;
use RestApiToolkit\REST\Exceptions\AuthorizationException;
use RestApiToolkit\REST\Exceptions\ConflictException;
use RestApiToolkit\REST\Exceptions\NotFoundException;
use RestApiToolkit\WordPress\Transformers\UserResource;

/**
 * CRUD de usuarios con protección explícita contra escalada de privilegios.
 */
class UsersController
{
    public function index(ApiRequest $request): \WP_REST_Response
    {
        $v = $request->validated();

        $args = [
            'number' => $v['per_page'],
            'paged'  => $v['page'],
        ];
        if (!empty($v['search'])) {
            $args['search'] = '*' . $v['search'] . '*';
        }
        if (!empty($v['role'])) {
            $args['role'] = $v['role'];
        }

        $query = new \WP_User_Query($args);
        $items = array_map(static function (\WP_User $user) {
            return UserResource::make($user, current_user_can('edit_users'));
        }, $query->get_results());

        return ApiResponse::paginated($items, $v['page'], $v['per_page'], (int) $query->get_total());
    }

    public function show(ApiRequest $request): array
    {
        $user = $this->findUser($request);

        $withEmail = current_user_can('edit_users') || get_current_user_id() === $user->ID;

        return UserResource::make($user, $withEmail);
    }

    public function store(ApiRequest $request): \WP_REST_Response
    {
        $v = $request->validated();

        if (username_exists($v['username']) || email_exists($v['email'])) {
            throw new ConflictException(__('El usuario o email ya existe.', 'rest-api-toolkit'), 'user_exists');
        }

        $role = $this->assertAssignableRole($v['role'] ?? get_option('default_role', 'subscriber'));

        $userId = wp_insert_user([
            'user_login'   => $v['username'],
            'user_email'   => $v['email'],
            'user_pass'    => $v['password'] ?? wp_generate_password(24),
            'first_name'   => $v['first_name'] ?? '',
            'last_name'    => $v['last_name'] ?? '',
            'display_name' => $v['display_name'] ?? $v['username'],
            'role'         => $role,
        ]);

        if (is_wp_error($userId)) {
            throw new ApiException($userId->get_error_message(), 'user_create_failed', 422);
        }

        return ApiResponse::created(UserResource::make(get_user_by('id', $userId), true));
    }

    public function update(ApiRequest $request): array
    {
        $user = $this->findUser($request);

        if (!current_user_can('edit_user', $user->ID)) {
            throw new AuthorizationException();
        }

        $v    = $request->validated();
        $data = ['ID' => $user->ID];

        foreach (['email' => 'user_email', 'first_name' => 'first_name', 'last_name' => 'last_name', 'display_name' => 'display_name', 'password' => 'user_pass'] as $field => $column) {
            if (isset($v[$field])) {
                $data[$column] = $v[$field];
            }
        }

        // Cambiar rol exige promote_users y nunca permite auto-escalada.
        if (isset($v['role'])) {
            if (!current_user_can('promote_users')) {
                throw new AuthorizationException(__('No puedes cambiar roles de usuario.', 'rest-api-toolkit'));
            }
            $data['role'] = $this->assertAssignableRole($v['role']);
        }

        $result = wp_update_user($data);
        if (is_wp_error($result)) {
            throw new ApiException($result->get_error_message(), 'user_update_failed', 422);
        }

        return UserResource::make(get_user_by('id', $user->ID), true);
    }

    public function destroy(ApiRequest $request): \WP_REST_Response
    {
        $user = $this->findUser($request);

        if (!current_user_can('delete_user', $user->ID) || $user->ID === get_current_user_id()) {
            throw new AuthorizationException();
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';

        $reassign = (int) $request->query('reassign', 0);
        if (!wp_delete_user($user->ID, $reassign ?: null)) {
            throw new ApiException(__('No se pudo eliminar el usuario.', 'rest-api-toolkit'), 'user_delete_failed', 500);
        }

        return ApiResponse::success(['deleted' => true, 'id' => $user->ID]);
    }

    private function findUser(ApiRequest $request): \WP_User
    {
        $user = get_user_by('id', (int) $request->route('id'));
        if (!$user) {
            throw new NotFoundException(__('Usuario no encontrado.', 'rest-api-toolkit'), 'user_not_found');
        }

        return $user;
    }

    /**
     * Un usuario nunca puede asignar un rol con más privilegios que los suyos.
     */
    private function assertAssignableRole(string $role): string
    {
        $role = sanitize_key($role);

        $editable = get_editable_roles();
        if (!array_key_exists($role, $editable)) {
            throw new AuthorizationException(
                sprintf(__('No puedes asignar el rol "%s".', 'rest-api-toolkit'), $role)
            );
        }

        if ($role === 'administrator' && !current_user_can('administrator')) {
            throw new AuthorizationException(__('Solo un administrador puede asignar ese rol.', 'rest-api-toolkit'));
        }

        return $role;
    }
}
