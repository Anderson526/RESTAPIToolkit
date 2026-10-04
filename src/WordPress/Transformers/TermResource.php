<?php

namespace RestApiToolkit\WordPress\Transformers;

class TermResource
{
    public static function make(\WP_Term $term): array
    {
        $data = [
            'id'          => $term->term_id,
            'name'        => $term->name,
            'slug'        => $term->slug,
            'taxonomy'    => $term->taxonomy,
            'description' => $term->description,
            'parent'      => $term->parent,
            'count'       => $term->count,
            'link'        => get_term_link($term),
        ];

        return apply_filters('rat_term_response', $data, $term);
    }
}
