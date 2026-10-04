<?php

namespace RestApiToolkit\WordPress\Transformers;

class MediaResource
{
    public static function make(\WP_Post $attachment): array
    {
        $meta = wp_get_attachment_metadata($attachment->ID) ?: [];

        $data = [
            'id'        => $attachment->ID,
            'title'     => get_the_title($attachment),
            'alt'       => (string) get_post_meta($attachment->ID, '_wp_attachment_image_alt', true),
            'caption'   => $attachment->post_excerpt,
            'mime_type' => $attachment->post_mime_type,
            'url'       => wp_get_attachment_url($attachment->ID),
            'sizes'     => isset($meta['sizes']) ? array_keys((array) $meta['sizes']) : [],
            'width'     => $meta['width'] ?? null,
            'height'    => $meta['height'] ?? null,
            'date'      => mysql2date('c', $attachment->post_date_gmt, false),
            'author'    => (int) $attachment->post_author,
        ];

        return apply_filters('rat_media_response', $data, $attachment);
    }
}
