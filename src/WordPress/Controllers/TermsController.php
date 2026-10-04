<?php

namespace RestApiToolkit\WordPress\Controllers;

use RestApiToolkit\REST\ApiRequest;
use RestApiToolkit\REST\ApiResponse;
use RestApiToolkit\REST\Exceptions\ApiException;
use RestApiToolkit\REST\Exceptions\NotFoundException;
use RestApiToolkit\WordPress\Transformers\TermResource;

/**
 * CRUD de términos para cualquier taxonomía expuesta:
 * /taxonomies/{taxonomy}/terms[/{id}]
 * (incluye category, post_tag, product_cat, product_tag, etc.)
 */
class TermsController
{
    public function index(ApiRequest $request): \WP_REST_Response
    {
        $taxonomy = $this->resolveTaxonomy($request);
        $v        = $request->validated();

        $args = [
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'number'     => $v['per_page'],
            'offset'     => ($v['page'] - 1) * $v['per_page'],
            'search'     => $v['search'] ?? '',
        ];

        $terms = get_terms($args);
        if (is_wp_error($terms)) {
            throw new ApiException($terms->get_error_message(), 'terms_query_failed', 500);
        }

        $total = (int) wp_count_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);

        return ApiResponse::paginated(
            array_map([TermResource::class, 'make'], $terms),
            $v['page'],
            $v['per_page'],
            $total
        );
    }

    public function show(ApiRequest $request): array
    {
        return TermResource::make($this->findTerm($request));
    }

    public function store(ApiRequest $request): \WP_REST_Response
    {
        $taxonomy = $this->resolveTaxonomy($request);
        $v        = $request->validated();

        $result = wp_insert_term($v['name'], $taxonomy, [
            'slug'        => $v['slug'] ?? '',
            'description' => $v['description'] ?? '',
            'parent'      => $v['parent'] ?? 0,
        ]);

        if (is_wp_error($result)) {
            throw new ApiException($result->get_error_message(), 'term_create_failed', 422);
        }

        return ApiResponse::created(TermResource::make(get_term($result['term_id'], $taxonomy)));
    }

    public function update(ApiRequest $request): array
    {
        $term = $this->findTerm($request);
        $v    = $request->validated();

        $args = [];
        foreach (['name', 'slug', 'description', 'parent'] as $field) {
            if (isset($v[$field])) {
                $args[$field] = $v[$field];
            }
        }

        $result = wp_update_term($term->term_id, $term->taxonomy, $args);
        if (is_wp_error($result)) {
            throw new ApiException($result->get_error_message(), 'term_update_failed', 422);
        }

        return TermResource::make(get_term($term->term_id, $term->taxonomy));
    }

    public function destroy(ApiRequest $request): \WP_REST_Response
    {
        $term = $this->findTerm($request);

        $result = wp_delete_term($term->term_id, $term->taxonomy);
        if (is_wp_error($result) || !$result) {
            throw new ApiException(__('No se pudo eliminar el término.', 'rest-api-toolkit'), 'term_delete_failed', 500);
        }

        return ApiResponse::success(['deleted' => true, 'id' => $term->term_id]);
    }

    private function resolveTaxonomy(ApiRequest $request): string
    {
        $taxonomy = sanitize_key((string) $request->route('taxonomy'));

        if (!taxonomy_exists($taxonomy)) {
            throw new NotFoundException(
                sprintf(__('La taxonomía "%s" no existe.', 'rest-api-toolkit'), $taxonomy),
                'taxonomy_not_found'
            );
        }

        $object = get_taxonomy($taxonomy);
        if (!$object || (!$object->public && !$object->show_in_rest)) {
            throw new NotFoundException(
                sprintf(__('La taxonomía "%s" no está expuesta en la API.', 'rest-api-toolkit'), $taxonomy),
                'taxonomy_not_exposed'
            );
        }

        return $taxonomy;
    }

    private function findTerm(ApiRequest $request): \WP_Term
    {
        $taxonomy = $this->resolveTaxonomy($request);
        $term     = get_term((int) $request->route('id'), $taxonomy);

        if (!$term instanceof \WP_Term) {
            throw new NotFoundException(__('Término no encontrado.', 'rest-api-toolkit'), 'term_not_found');
        }

        return $term;
    }
}
