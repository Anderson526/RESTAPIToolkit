<?php

namespace RestApiToolkit\WordPress\Controllers;

use RestApiToolkit\REST\ApiRequest;
use RestApiToolkit\REST\Exceptions\NotFoundException;

/**
 * CRUD dinámico para Custom Post Types: /cpt/{type} y /cpt/{type}/{id}.
 */
class CptController extends PostsController
{
    protected function resolveType(ApiRequest $request): string
    {
        $type = sanitize_key((string) $request->route('type'));

        if (!post_type_exists($type)) {
            throw new NotFoundException(
                sprintf(__('El post type "%s" no está registrado.', 'rest-api-toolkit'), $type),
                'cpt_not_found'
            );
        }

        $object   = get_post_type_object($type);
        $exposed  = $object && ($object->public || $object->show_in_rest);
        $internal = in_array($type, ['revision', 'attachment', 'nav_menu_item'], true);

        if (!$exposed || $internal) {
            throw new NotFoundException(
                sprintf(__('El post type "%s" no está expuesto en la API.', 'rest-api-toolkit'), $type),
                'cpt_not_exposed'
            );
        }

        return $type;
    }
}
