<?php

namespace RestApiToolkit\WordPress\Controllers;

use RestApiToolkit\REST\ApiRequest;
use RestApiToolkit\REST\ApiResponse;
use RestApiToolkit\REST\Exceptions\ApiException;
use RestApiToolkit\REST\Exceptions\NotFoundException;
use RestApiToolkit\REST\Exceptions\ValidationException;
use RestApiToolkit\WordPress\Transformers\CommentResource;

class CommentsController
{
    public function index(ApiRequest $request): \WP_REST_Response
    {
        $v = $request->validated();

        $args = [
            'status'  => current_user_can('moderate_comments') && !empty($v['status']) ? $v['status'] : 'approve',
            'number'  => $v['per_page'],
            'paged'   => $v['page'],
            'post_id' => $v['post_id'] ?? 0,
        ];

        $comments = get_comments($args);
        $total    = (int) get_comments(array_merge($args, ['count' => true, 'number' => 0, 'paged' => 1]));

        return ApiResponse::paginated(
            array_map([CommentResource::class, 'make'], $comments),
            $v['page'],
            $v['per_page'],
            $total
        );
    }

    public function store(ApiRequest $request): \WP_REST_Response
    {
        $v    = $request->validated();
        $post = get_post((int) $v['post_id']);

        if (!$post || $post->post_status !== 'publish') {
            throw new NotFoundException(__('Post no encontrado.', 'rest-api-toolkit'), 'post_not_found');
        }
        if (!comments_open($post)) {
            throw new ValidationException(['post_id' => [__('Los comentarios están cerrados para este post.', 'rest-api-toolkit')]]);
        }

        $user = wp_get_current_user();
        $data = [
            'comment_post_ID'      => $post->ID,
            'comment_content'      => $v['content'],
            'comment_parent'       => $v['parent'] ?? 0,
            'user_id'              => $user->exists() ? $user->ID : 0,
            'comment_author'       => $user->exists() ? $user->display_name : ($v['author_name'] ?? ''),
            'comment_author_email' => $user->exists() ? $user->user_email : ($v['author_email'] ?? ''),
            'comment_author_url'   => '',
        ];

        if (!$user->exists() && (empty($data['comment_author']) || empty($data['comment_author_email']))) {
            throw new ValidationException([
                'author_name'  => [__('Obligatorio para comentarios anónimos.', 'rest-api-toolkit')],
                'author_email' => [__('Obligatorio para comentarios anónimos.', 'rest-api-toolkit')],
            ]);
        }

        // wp_new_comment aplica moderación, blacklist y flood control del core.
        $commentId = wp_new_comment(wp_slash($data), true);

        if (is_wp_error($commentId)) {
            throw new ApiException($commentId->get_error_message(), 'comment_create_failed', 422);
        }

        return ApiResponse::created(CommentResource::make(get_comment($commentId)));
    }

    public function update(ApiRequest $request): array
    {
        $comment = $this->findComment($request);
        $v       = $request->validated();

        if (isset($v['status'])) {
            wp_set_comment_status($comment->comment_ID, $v['status']);
        }
        if (isset($v['content'])) {
            wp_update_comment(wp_slash([
                'comment_ID'      => $comment->comment_ID,
                'comment_content' => $v['content'],
            ]));
        }

        return CommentResource::make(get_comment($comment->comment_ID));
    }

    public function destroy(ApiRequest $request): \WP_REST_Response
    {
        $comment = $this->findComment($request);

        if (!wp_delete_comment($comment->comment_ID, true)) {
            throw new ApiException(__('No se pudo eliminar el comentario.', 'rest-api-toolkit'), 'comment_delete_failed', 500);
        }

        return ApiResponse::success(['deleted' => true, 'id' => (int) $comment->comment_ID]);
    }

    private function findComment(ApiRequest $request): \WP_Comment
    {
        $comment = get_comment((int) $request->route('id'));
        if (!$comment) {
            throw new NotFoundException(__('Comentario no encontrado.', 'rest-api-toolkit'), 'comment_not_found');
        }

        return $comment;
    }
}
