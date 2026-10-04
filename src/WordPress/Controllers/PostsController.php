<?php

namespace RestApiToolkit\WordPress\Controllers;

use RestApiToolkit\REST\ApiRequest;
use RestApiToolkit\REST\ApiResponse;
use RestApiToolkit\REST\Exceptions\ApiException;
use RestApiToolkit\REST\Exceptions\AuthorizationException;
use RestApiToolkit\REST\Exceptions\NotFoundException;
use RestApiToolkit\WordPress\Transformers\PostResource;

/**
 * CRUD de posts. PagesController y CptController reutilizan esta lógica.
 */
class PostsController
{
    /** @var string */
    protected $postType = 'post';

    /** Resuelve el post type del request (los CPT lo sobreescriben). */
    protected function resolveType(ApiRequest $request): string
    {
        return $this->postType;
    }

    public function index(ApiRequest $request): \WP_REST_Response
    {
        $v    = $request->validated();
        $type = $this->resolveType($request);

        $args = [
            'post_type'      => $type,
            'post_status'    => 'publish',
            'paged'          => $v['page'],
            'posts_per_page' => $v['per_page'],
            's'              => $v['search'] ?? '',
            'orderby'        => $v['orderby'] ?? 'date',
            'order'          => strtoupper($v['order'] ?? 'DESC'),
        ];

        // Solo editores pueden listar estados no publicados.
        if (!empty($v['status']) && current_user_can('edit_posts')) {
            $args['post_status'] = $v['status'];
        }
        if (!empty($v['author'])) {
            $args['author'] = $v['author'];
        }
        if (!empty($v['category']) && $type === 'post') {
            $args['category_name'] = $v['category'];
        }
        if (!empty($v['tag']) && $type === 'post') {
            $args['tag'] = $v['tag'];
        }

        $query = new \WP_Query($args);

        return ApiResponse::paginated(
            PostResource::collection($query->posts),
            $v['page'],
            $v['per_page'],
            (int) $query->found_posts
        );
    }

    public function show(ApiRequest $request): array
    {
        return PostResource::make($this->findPost($request));
    }

    public function store(ApiRequest $request): \WP_REST_Response
    {
        $v = $request->validated();

        $postId = wp_insert_post([
            'post_type'    => $this->resolveType($request),
            'post_title'   => $v['title'],
            'post_content' => $v['content'] ?? '',
            'post_excerpt' => $v['excerpt'] ?? '',
            'post_status'  => $v['status'] ?? 'draft',
            'post_author'  => get_current_user_id(),
        ], true);

        if (is_wp_error($postId)) {
            throw new ApiException($postId->get_error_message(), 'post_create_failed', 422);
        }

        $this->syncTerms($postId, $v);

        return ApiResponse::created(PostResource::make(get_post($postId)));
    }

    public function update(ApiRequest $request): array
    {
        $post = $this->findPost($request, true);

        if (!current_user_can('edit_post', $post->ID)) {
            throw new AuthorizationException();
        }

        $v    = $request->validated();
        $data = ['ID' => $post->ID];

        foreach (['title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt', 'status' => 'post_status'] as $field => $column) {
            if (array_key_exists($field, $v)) {
                $data[$column] = $v[$field];
            }
        }

        $result = wp_update_post($data, true);
        if (is_wp_error($result)) {
            throw new ApiException($result->get_error_message(), 'post_update_failed', 422);
        }

        $this->syncTerms($post->ID, $v);

        return PostResource::make(get_post($post->ID));
    }

    public function destroy(ApiRequest $request): \WP_REST_Response
    {
        $post = $this->findPost($request, true);

        if (!current_user_can('delete_post', $post->ID)) {
            throw new AuthorizationException();
        }

        $force  = filter_var($request->query('force', false), FILTER_VALIDATE_BOOLEAN);
        $result = $force ? wp_delete_post($post->ID, true) : wp_trash_post($post->ID);

        if (!$result) {
            throw new ApiException(__('No se pudo eliminar el recurso.', 'rest-api-toolkit'), 'post_delete_failed', 500);
        }

        return ApiResponse::success(['deleted' => true, 'id' => $post->ID, 'force' => $force]);
    }

    /**
     * @throws NotFoundException Si no existe, no coincide el tipo o no es visible para el usuario.
     */
    protected function findPost(ApiRequest $request, bool $forEdit = false): \WP_Post
    {
        $post = get_post((int) $request->route('id'));
        $type = $this->resolveType($request);

        if (!$post || $post->post_type !== $type) {
            throw new NotFoundException(__('Contenido no encontrado.', 'rest-api-toolkit'), $type . '_not_found');
        }

        $visible = $post->post_status === 'publish' || current_user_can('edit_post', $post->ID);
        if (!$forEdit && !$visible) {
            throw new NotFoundException(__('Contenido no encontrado.', 'rest-api-toolkit'), $type . '_not_found');
        }

        return $post;
    }

    private function syncTerms(int $postId, array $v): void
    {
        if (isset($v['categories'])) {
            wp_set_post_categories($postId, array_map('intval', (array) $v['categories']));
        }
        if (isset($v['tags'])) {
            wp_set_post_tags($postId, array_map('intval', (array) $v['tags']));
        }
    }
}
