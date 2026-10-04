<?php

namespace RestApiToolkit\WordPress\Controllers;

use RestApiToolkit\REST\ApiRequest;
use RestApiToolkit\REST\ApiResponse;
use RestApiToolkit\REST\Exceptions\ApiException;
use RestApiToolkit\REST\Exceptions\AuthorizationException;
use RestApiToolkit\REST\Exceptions\NotFoundException;
use RestApiToolkit\REST\Exceptions\ValidationException;
use RestApiToolkit\WordPress\Transformers\MediaResource;

class MediaController
{
    public function index(ApiRequest $request): \WP_REST_Response
    {
        $v = $request->validated();

        $query = new \WP_Query([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'paged'          => $v['page'],
            'posts_per_page' => $v['per_page'],
            's'              => $v['search'] ?? '',
            'post_mime_type' => $v['mime_type'] ?? '',
        ]);

        return ApiResponse::paginated(
            array_map([MediaResource::class, 'make'], $query->posts),
            $v['page'],
            $v['per_page'],
            (int) $query->found_posts
        );
    }

    public function show(ApiRequest $request): array
    {
        return MediaResource::make($this->findAttachment($request));
    }

    public function store(ApiRequest $request): \WP_REST_Response
    {
        $file = $request->file('file');
        if (!$file) {
            throw new ValidationException(['file' => [__('El archivo es obligatorio (multipart/form-data, campo "file").', 'rest-api-toolkit')]]);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // media_handle_sideload valida MIME real, extensión y tamaño máximo de WP.
        $attachmentId = media_handle_sideload($file, 0, $request->validated('title'));

        if (is_wp_error($attachmentId)) {
            throw new ApiException($attachmentId->get_error_message(), 'upload_failed', 422);
        }

        $alt = $request->validated('alt');
        if ($alt) {
            update_post_meta($attachmentId, '_wp_attachment_image_alt', $alt);
        }

        return ApiResponse::created(MediaResource::make(get_post($attachmentId)));
    }

    public function destroy(ApiRequest $request): \WP_REST_Response
    {
        $attachment = $this->findAttachment($request);

        if (!current_user_can('delete_post', $attachment->ID)) {
            throw new AuthorizationException();
        }

        if (!wp_delete_attachment($attachment->ID, true)) {
            throw new ApiException(__('No se pudo eliminar el archivo.', 'rest-api-toolkit'), 'media_delete_failed', 500);
        }

        return ApiResponse::success(['deleted' => true, 'id' => $attachment->ID]);
    }

    private function findAttachment(ApiRequest $request): \WP_Post
    {
        $post = get_post((int) $request->route('id'));
        if (!$post || $post->post_type !== 'attachment') {
            throw new NotFoundException(__('Archivo no encontrado.', 'rest-api-toolkit'), 'media_not_found');
        }

        return $post;
    }
}
