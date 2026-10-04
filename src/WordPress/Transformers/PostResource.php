<?php

namespace RestApiToolkit\WordPress\Transformers;

/**
 * Transforma WP_Post a array de salida. Nunca se exponen objetos internos.
 */
class PostResource
{
    public static function make(\WP_Post $post): array
    {
        $data = [
            'id'         => $post->ID,
            'type'       => $post->post_type,
            'slug'       => $post->post_name,
            'status'     => $post->post_status,
            'title'      => get_the_title($post),
            'content'    => apply_filters('the_content', $post->post_content),
            'excerpt'    => get_the_excerpt($post),
            'author'     => (int) $post->post_author,
            'date'       => mysql2date('c', $post->post_date_gmt, false),
            'modified'   => mysql2date('c', $post->post_modified_gmt, false),
            'link'       => get_permalink($post),
            'featured_media' => (int) get_post_thumbnail_id($post),
            'categories' => wp_get_post_categories($post->ID),
            'tags'       => wp_get_post_tags($post->ID, ['fields' => 'ids']),
        ];

        return apply_filters('rat_post_response', $data, $post);
    }

    /** @param \WP_Post[] $posts */
    public static function collection(array $posts): array
    {
        return array_map([self::class, 'make'], $posts);
    }
}
