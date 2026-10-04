<?php

namespace RestApiToolkit\WordPress\Transformers;

class CommentResource
{
    public static function make(\WP_Comment $comment): array
    {
        $data = [
            'id'        => (int) $comment->comment_ID,
            'post_id'   => (int) $comment->comment_post_ID,
            'parent'    => (int) $comment->comment_parent,
            'author'    => $comment->comment_author,
            'content'   => $comment->comment_content,
            'status'    => wp_get_comment_status($comment),
            'date'      => mysql2date('c', $comment->comment_date_gmt, false),
            'user_id'   => (int) $comment->user_id,
        ];

        return apply_filters('rat_comment_response', $data, $comment);
    }
}
