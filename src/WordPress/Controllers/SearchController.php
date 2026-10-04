<?php

namespace RestApiToolkit\WordPress\Controllers;

use RestApiToolkit\REST\ApiRequest;
use RestApiToolkit\REST\ApiResponse;
use RestApiToolkit\WordPress\Transformers\PostResource;

/**
 * Búsqueda global: GET /search?q=...&post_type=...
 */
class SearchController
{
    public function index(ApiRequest $request): \WP_REST_Response
    {
        $v = $request->validated();

        $postTypes = ['post', 'page'];
        if (!empty($v['post_type'])) {
            $requested = array_map('sanitize_key', explode(',', $v['post_type']));
            $postTypes = array_values(array_filter($requested, static function ($type) {
                $object = get_post_type_object($type);
                return $object && ($object->public || $object->show_in_rest);
            }));
            if (!$postTypes) {
                $postTypes = ['post'];
            }
        }

        $args = [
            's'              => $v['q'],
            'post_type'      => $postTypes,
            'post_status'    => 'publish',
            'paged'          => $v['page'],
            'posts_per_page' => $v['per_page'],
        ];

        /** Permite extender los argumentos de búsqueda. */
        $args = apply_filters('rat_search_args', $args, $request);

        $query = new \WP_Query($args);

        return ApiResponse::paginated(
            PostResource::collection($query->posts),
            $v['page'],
            $v['per_page'],
            (int) $query->found_posts
        );
    }
}
