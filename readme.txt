=== REST API Toolkit ===
Contributors: restapitoolkit
Donate link: https://www.paypal.com/donate
Tags: rest api, api, woocommerce, developer, json
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Capa de infraestructura REST para WordPress y WooCommerce.

== Description ==

REST API Toolkit convierte tu instalación de WordPress en una plataforma API robusta:

* Router REST propio sobre register_rest_route() con versionado (/wp-json/rat/v1/).
* Autenticación: cookies + nonce, Application Passwords y API Keys con scopes.
* Autorización basada en capabilities de WordPress y scopes de API.
* Validación y sanitización de requests mediante schemas declarativos.
* Respuestas y errores estandarizados (success/data/meta, error codes).
* Rate limiting por API key, usuario o IP con headers X-RateLimit-*.
* Logging de requests con panel de administración y limpieza automática.
* CRUD de recursos WordPress: posts, pages, CPT, usuarios, media, taxonomías, comentarios y búsqueda.
* CRUD de recursos WooCommerce: products, variations, orders y customers (módulo desacoplado).
* Documentación OpenAPI 3 autogenerada en /wp-json/rat/v1/openapi.json.
* Panel de administración: dashboard, API keys, logs, ajustes y donaciones.

== Installation ==

1. Sube la carpeta `restapitoolkit` a `/wp-content/plugins/`.
2. Activa el plugin desde el menú Plugins.
3. Configura las opciones en "REST API Toolkit" del menú de administración.

== Frequently Asked Questions ==

= ¿Funciona sin WooCommerce? =

Sí. El módulo WooCommerce se desactiva automáticamente si WooCommerce no está instalado.

== Changelog ==

= 0.1.0 =
* Versión inicial (MVP): core, router, validación, autenticación, API keys, rate limiting, logging, recursos WordPress y WooCommerce, OpenAPI, admin UI y sección de donaciones.
