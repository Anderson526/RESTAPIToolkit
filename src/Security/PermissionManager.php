<?php

namespace RestApiToolkit\Security;

use RestApiToolkit\REST\ApiRequest;
use RestApiToolkit\REST\Exceptions\AuthenticationException;
use RestApiToolkit\REST\Exceptions\AuthorizationException;

/**
 * Autorización: scopes de API key + capabilities de WordPress.
 * Permisos con formato "recurso.accion", ej: products.read, orders.update.
 */
class PermissionManager
{
    /** @var array<string, string> permiso => capability */
    private $capabilityMap = [
        'posts.create'      => 'publish_posts',
        'posts.update'      => 'edit_posts',
        'posts.delete'      => 'delete_posts',
        'pages.create'      => 'publish_pages',
        'pages.update'      => 'edit_pages',
        'pages.delete'      => 'delete_pages',
        'cpt.create'        => 'publish_posts',
        'cpt.update'        => 'edit_posts',
        'cpt.delete'        => 'delete_posts',
        'users.read'        => 'list_users',
        'users.create'      => 'create_users',
        'users.update'      => 'edit_users',
        'users.delete'      => 'delete_users',
        'media.create'      => 'upload_files',
        'media.delete'      => 'delete_posts',
        'terms.manage'      => 'manage_categories',
        'comments.moderate' => 'moderate_comments',
        'products.create'   => 'manage_woocommerce',
        'products.update'   => 'manage_woocommerce',
        'products.delete'   => 'manage_woocommerce',
        'orders.read'       => 'manage_woocommerce',
        'orders.create'     => 'manage_woocommerce',
        'orders.update'     => 'manage_woocommerce',
        'orders.delete'     => 'manage_woocommerce',
        'customers.read'    => 'manage_woocommerce',
        'customers.create'  => 'manage_woocommerce',
        'customers.update'  => 'manage_woocommerce',
        'customers.delete'  => 'manage_woocommerce',
    ];

    /**
     * @throws AuthenticationException|AuthorizationException
     */
    public function authorize(ApiRequest $request, string $permission): void
    {
        $user = $request->user();
        if (!$user || !$user->exists()) {
            throw new AuthenticationException();
        }

        $apiKey = $request->apiKey();
        if ($apiKey && !$this->scopeAllows((array) $apiKey->scopes, $permission)) {
            throw new AuthorizationException(
                sprintf(__('La API key no tiene el scope "%s".', 'rest-api-toolkit'), $permission)
            );
        }

        $capability = $this->capabilityMap[$permission] ?? null;

        /**
         * Permite personalizar la capability requerida para un permiso.
         *
         * @param string|null $capability
         * @param string      $permission
         * @param ApiRequest  $request
         */
        $capability = apply_filters('rat_permission_capability', $capability, $permission, $request);

        if ($capability && !user_can($user, $capability)) {
            throw new AuthorizationException();
        }
    }

    /**
     * Comprueba si los scopes cubren el permiso. Soporta '*' y comodines "recurso.*".
     *
     * @param string[] $scopes
     */
    public function scopeAllows(array $scopes, string $permission): bool
    {
        if (in_array('*', $scopes, true) || in_array($permission, $scopes, true)) {
            return true;
        }

        $resource = strtok($permission, '.');

        return in_array($resource . '.*', $scopes, true);
    }
}
