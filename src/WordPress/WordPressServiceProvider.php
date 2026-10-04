<?php

namespace RestApiToolkit\WordPress;

use RestApiToolkit\Documentation\OpenApiController;
use RestApiToolkit\REST\Router;
use RestApiToolkit\WordPress\Controllers\CommentsController;
use RestApiToolkit\WordPress\Controllers\CptController;
use RestApiToolkit\WordPress\Controllers\MediaController;
use RestApiToolkit\WordPress\Controllers\PagesController;
use RestApiToolkit\WordPress\Controllers\PostsController;
use RestApiToolkit\WordPress\Controllers\SearchController;
use RestApiToolkit\WordPress\Controllers\TermsController;
use RestApiToolkit\WordPress\Controllers\UsersController;

/**
 * Registra las rutas de recursos WordPress con sus schemas de validación.
 */
class WordPressServiceProvider
{
    public static function register(Router $router): void
    {
        // ── Posts ────────────────────────────────────────────────────────
        $router->get('/posts', PostsController::class . '@index', [
            'public'  => true,
            'summary' => 'Listar posts',
            'schema'  => self::listSchema([
                'status'   => ['type' => 'string', 'enum' => ['publish', 'draft', 'pending', 'private', 'future']],
                'author'   => ['type' => 'integer', 'min' => 1],
                'category' => ['type' => 'string', 'max_length' => 100],
                'tag'      => ['type' => 'string', 'max_length' => 100],
            ]),
        ]);
        $router->get('/posts/{id}', PostsController::class . '@show', ['public' => true, 'summary' => 'Obtener un post']);
        $router->post('/posts', PostsController::class . '@store', [
            'permission' => 'posts.create',
            'summary'    => 'Crear post',
            'schema'     => self::postSchema(true),
        ]);
        $router->put('/posts/{id}', PostsController::class . '@update', [
            'permission' => 'posts.update',
            'summary'    => 'Actualizar post',
            'schema'     => self::postSchema(false),
        ]);
        $router->delete('/posts/{id}', PostsController::class . '@destroy', [
            'permission' => 'posts.delete',
            'summary'    => 'Eliminar post',
        ]);

        // ── Pages ────────────────────────────────────────────────────────
        $router->get('/pages', PagesController::class . '@index', [
            'public'  => true,
            'summary' => 'Listar páginas',
            'schema'  => self::listSchema(['status' => ['type' => 'string', 'enum' => ['publish', 'draft', 'pending', 'private']]]),
        ]);
        $router->get('/pages/{id}', PagesController::class . '@show', ['public' => true, 'summary' => 'Obtener una página']);
        $router->post('/pages', PagesController::class . '@store', [
            'permission' => 'pages.create',
            'summary'    => 'Crear página',
            'schema'     => self::postSchema(true),
        ]);
        $router->put('/pages/{id}', PagesController::class . '@update', [
            'permission' => 'pages.update',
            'summary'    => 'Actualizar página',
            'schema'     => self::postSchema(false),
        ]);
        $router->delete('/pages/{id}', PagesController::class . '@destroy', [
            'permission' => 'pages.delete',
            'summary'    => 'Eliminar página',
        ]);

        // ── Custom Post Types ───────────────────────────────────────────
        $router->get('/cpt/{type}', CptController::class . '@index', [
            'public'  => true,
            'summary' => 'Listar entradas de un CPT',
            'schema'  => self::listSchema(),
        ]);
        $router->get('/cpt/{type}/{id}', CptController::class . '@show', ['public' => true, 'summary' => 'Obtener entrada de un CPT']);
        $router->post('/cpt/{type}', CptController::class . '@store', [
            'permission' => 'cpt.create',
            'summary'    => 'Crear entrada en un CPT',
            'schema'     => self::postSchema(true),
        ]);
        $router->put('/cpt/{type}/{id}', CptController::class . '@update', [
            'permission' => 'cpt.update',
            'summary'    => 'Actualizar entrada de un CPT',
            'schema'     => self::postSchema(false),
        ]);
        $router->delete('/cpt/{type}/{id}', CptController::class . '@destroy', [
            'permission' => 'cpt.delete',
            'summary'    => 'Eliminar entrada de un CPT',
        ]);

        // ── Users ────────────────────────────────────────────────────────
        $router->get('/users', UsersController::class . '@index', [
            'permission' => 'users.read',
            'summary'    => 'Listar usuarios',
            'schema'     => self::listSchema(['role' => ['type' => 'string', 'sanitize' => 'key']]),
        ]);
        $router->get('/users/{id}', UsersController::class . '@show', [
            'permission' => 'users.read',
            'summary'    => 'Obtener un usuario',
        ]);
        $router->post('/users', UsersController::class . '@store', [
            'permission' => 'users.create',
            'summary'    => 'Crear usuario',
            'schema'     => [
                'username'     => ['required' => true, 'type' => 'string', 'min_length' => 3, 'max_length' => 60, 'regex' => '/^[a-zA-Z0-9._\-]+$/'],
                'email'        => ['required' => true, 'type' => 'email'],
                'password'     => ['type' => 'string', 'min_length' => 12, 'max_length' => 128, 'sanitize' => 'none'],
                'first_name'   => ['type' => 'string', 'max_length' => 100],
                'last_name'    => ['type' => 'string', 'max_length' => 100],
                'display_name' => ['type' => 'string', 'max_length' => 150],
                'role'         => ['type' => 'string', 'sanitize' => 'key'],
            ],
        ]);
        $router->patch('/users/{id}', UsersController::class . '@update', [
            'permission' => 'users.update',
            'summary'    => 'Actualizar usuario',
            'schema'     => [
                'email'        => ['type' => 'email'],
                'password'     => ['type' => 'string', 'min_length' => 12, 'max_length' => 128, 'sanitize' => 'none'],
                'first_name'   => ['type' => 'string', 'max_length' => 100],
                'last_name'    => ['type' => 'string', 'max_length' => 100],
                'display_name' => ['type' => 'string', 'max_length' => 150],
                'role'         => ['type' => 'string', 'sanitize' => 'key'],
            ],
        ]);
        $router->delete('/users/{id}', UsersController::class . '@destroy', [
            'permission' => 'users.delete',
            'summary'    => 'Eliminar usuario',
        ]);

        // ── Media ────────────────────────────────────────────────────────
        $router->get('/media', MediaController::class . '@index', [
            'public'  => true,
            'summary' => 'Listar archivos multimedia',
            'schema'  => self::listSchema(['mime_type' => ['type' => 'string', 'max_length' => 100]]),
        ]);
        $router->get('/media/{id}', MediaController::class . '@show', ['public' => true, 'summary' => 'Obtener archivo multimedia']);
        $router->post('/media', MediaController::class . '@store', [
            'permission' => 'media.create',
            'summary'    => 'Subir archivo (multipart/form-data, campo "file")',
            'schema'     => [
                'title' => ['type' => 'string', 'max_length' => 200],
                'alt'   => ['type' => 'string', 'max_length' => 200],
            ],
        ]);
        $router->delete('/media/{id}', MediaController::class . '@destroy', [
            'permission' => 'media.delete',
            'summary'    => 'Eliminar archivo multimedia',
        ]);

        // ── Taxonomías / Terms ──────────────────────────────────────────
        $router->get('/taxonomies/{taxonomy}/terms', TermsController::class . '@index', [
            'public'  => true,
            'summary' => 'Listar términos de una taxonomía',
            'schema'  => self::listSchema(),
        ]);
        $router->get('/taxonomies/{taxonomy}/terms/{id}', TermsController::class . '@show', ['public' => true, 'summary' => 'Obtener término']);
        $router->post('/taxonomies/{taxonomy}/terms', TermsController::class . '@store', [
            'permission' => 'terms.manage',
            'summary'    => 'Crear término',
            'schema'     => self::termSchema(true),
        ]);
        $router->put('/taxonomies/{taxonomy}/terms/{id}', TermsController::class . '@update', [
            'permission' => 'terms.manage',
            'summary'    => 'Actualizar término',
            'schema'     => self::termSchema(false),
        ]);
        $router->delete('/taxonomies/{taxonomy}/terms/{id}', TermsController::class . '@destroy', [
            'permission' => 'terms.manage',
            'summary'    => 'Eliminar término',
        ]);

        // ── Comments ────────────────────────────────────────────────────
        $router->get('/comments', CommentsController::class . '@index', [
            'public'  => true,
            'summary' => 'Listar comentarios',
            'schema'  => self::listSchema([
                'post_id' => ['type' => 'integer', 'min' => 1],
                'status'  => ['type' => 'string', 'enum' => ['approve', 'hold', 'spam', 'trash', 'all']],
            ]),
        ]);
        $router->post('/comments', CommentsController::class . '@store', [
            'public'  => true,
            'summary' => 'Crear comentario (pasa por moderación del core)',
            'schema'  => [
                'post_id'      => ['required' => true, 'type' => 'integer', 'min' => 1],
                'content'      => ['required' => true, 'type' => 'string', 'sanitize' => 'html', 'max_length' => 65000],
                'parent'       => ['type' => 'integer', 'min' => 0, 'default' => 0],
                'author_name'  => ['type' => 'string', 'max_length' => 150],
                'author_email' => ['type' => 'email'],
            ],
        ]);
        $router->put('/comments/{id}', CommentsController::class . '@update', [
            'permission' => 'comments.moderate',
            'summary'    => 'Moderar/actualizar comentario',
            'schema'     => [
                'status'  => ['type' => 'string', 'enum' => ['approve', 'hold', 'spam', 'trash']],
                'content' => ['type' => 'string', 'sanitize' => 'html', 'max_length' => 65000],
            ],
        ]);
        $router->delete('/comments/{id}', CommentsController::class . '@destroy', [
            'permission' => 'comments.moderate',
            'summary'    => 'Eliminar comentario',
        ]);

        // ── Search ──────────────────────────────────────────────────────
        $router->get('/search', SearchController::class . '@index', [
            'public'  => true,
            'summary' => 'Búsqueda global de contenido',
            'schema'  => self::listSchema([
                'q'         => ['required' => true, 'type' => 'string', 'min_length' => 2, 'max_length' => 200],
                'post_type' => ['type' => 'string', 'max_length' => 200],
            ]),
        ]);

        // ── OpenAPI ─────────────────────────────────────────────────────
        $router->get('/openapi.json', OpenApiController::class . '@show', [
            'public'  => true,
            'summary' => 'Especificación OpenAPI 3 de la API',
        ]);
    }

    /** Schema base de paginación/orden para listados. */
    public static function listSchema(array $extra = []): array
    {
        return array_merge([
            'page'     => ['type' => 'integer', 'min' => 1, 'default' => 1],
            'per_page' => ['type' => 'integer', 'min' => 1, 'max' => 100, 'default' => 20],
            'search'   => ['type' => 'string', 'max_length' => 200],
            'orderby'  => ['type' => 'string', 'enum' => ['date', 'title', 'ID', 'modified'], 'default' => 'date'],
            'order'    => ['type' => 'string', 'enum' => ['asc', 'desc', 'ASC', 'DESC'], 'default' => 'desc'],
        ], $extra);
    }

    private static function postSchema(bool $isCreate): array
    {
        return [
            'title'      => ['required' => $isCreate, 'type' => 'string', 'max_length' => 200],
            'content'    => ['type' => 'string', 'sanitize' => 'html'],
            'excerpt'    => ['type' => 'string', 'sanitize' => 'html', 'max_length' => 5000],
            'status'     => ['type' => 'string', 'enum' => ['draft', 'publish', 'pending', 'private']] + ($isCreate ? ['default' => 'draft'] : []),
            'categories' => ['type' => 'array', 'items_type' => 'integer'],
            'tags'       => ['type' => 'array', 'items_type' => 'integer'],
        ];
    }

    private static function termSchema(bool $isCreate): array
    {
        return [
            'name'        => ['required' => $isCreate, 'type' => 'string', 'max_length' => 200],
            'slug'        => ['type' => 'string', 'sanitize' => 'key', 'max_length' => 200],
            'description' => ['type' => 'string', 'sanitize' => 'html', 'max_length' => 5000],
            'parent'      => ['type' => 'integer', 'min' => 0],
        ];
    }
}
