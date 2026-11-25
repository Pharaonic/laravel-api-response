# Maintaining This Package

This file records package-specific maintainer facts that are safe to keep in the public repository.

## Purpose

Consistent Laravel API responses: one request-local builder (`Response`) that resolves resources, paginators and Laravel's serializable types into a configurable envelope, with profiles and content negotiation, structured notifications, request IDs, optional SEO tags, and opt-in exception rendering.

## Supported Laravel lines

One branch per Laravel major, from `6.x` to the newest major, all carrying the same code. Each line's PHP range, `illuminate/*` constraint and tools follow the Pharaonic Laravel line table. `6.x` runs PHPUnit, because Pest cannot install on Laravel 6; later lines run Pest. `develop` follows the newest line.

Per-line differences beyond constraints:

- Cursor paginators exist from Laravel 8.41. `Pagination\CursorAdapter` names their contract by string, so it does nothing below; the cursor tests skip there.
- The exception renderer is registered differently per line, and the docs of each line show its own way: `render()` of the application handler on 6.x–7.x, `$this->renderable()` on 8.x–10.x, `withExceptions()` in `bootstrap/app.php` from 11.x.

## Core library

None. Everything here is Laravel glue: responses, resources, paginators, exception rendering and middleware.

## Public API invariants

Configuration keys (`pharaonic.api-response.*`), publish tags, and registered Blade, Artisan, macro, and validation names are public API.

- Public: `Response` (static constructors, fluent methods, `macro()` and `flushMacros()`), `Profiles\Profile` and its overridable methods, `Contracts\PaginationAdapter`, `Contracts\ExceptionMapper`, `ExceptionRenderer`, `Http\Middleware\AssignRequestId`, `Http\Middleware\UseResponseProfile`. Everything under `Support` and the built-in adapters under `Pagination` are `@internal`. There is no facade, helper or middleware alias.
- The canonical envelope fields (`success`, `message`, `code`, `data`, `errors`, `meta`, `notifications`, `seo`, `request_id`) and their order; `meta.pagination` and its groups (`type`, `pages`, `items` or `cursors`, `links`) and their fields per `type` (`length_aware`, `simple`, `cursor`); the built-in error codes (`VALIDATION_ERROR`, `UNAUTHENTICATED`, `FORBIDDEN`, `NOT_FOUND`, `PAGE_EXPIRED`, `SERVER_ERROR`, and the upper-snake status text of other HTTP exceptions).
- Fields that were never set are left out; values the caller set (`null`, `[]`, `false`, `0`, `''`) are always sent. Metadata merges resource `with()`/`additional()`, then `meta()`, then the package's `pagination`.
- A success keeps a status below 400 and an error one of 400 or more; 204 and 304 responses and HEAD requests have no body.
- The request attributes `pharaonic.api-response.request_id` and `pharaonic.api-response.profile`, and `extra.request_id` on log records.
- No SEO field without `withSEO()` on that response; the integration reads only `seo()->toArray()` through the `pharaonic-seo` binding and names no laravel-seo class.

## Package-specific constraints

- No request state outside the request: builders are plain objects, the request ID and route profile live in request attributes, and configuration is read at finalization. The log processor reads the current request when a record is written and is pushed once per Monolog instance (found among its processors), so a long-running worker never logs another request's ID.
- Finalization reads the builder's state and never changes it, so a builder can be finalized twice.
- JSON is encoded by the package with `JSON_THROW_ON_ERROR` and given to `JsonResponse::setJson()`: Laravel's `JsonResponse` constructor takes encoding options where Symfony's `fromJsonString()` passes a JSON flag (before Laravel 9), and `setEncodingOptions()` re-encodes the decoded data.
- `Str::orderedUuid()` needs `moontoast/math` with ramsey/uuid 3 (Laravel 6–7), so the only built-in request ID generator is `uuid`; other generators are invokable classes.
- `pharaonic/laravel-seo` is in `suggest` only; the CI `integrations` job installs it and runs the tests.

## Branch notes

Fixes land on the oldest affected line and are ported to every later line and `develop`.

## Documentation

https://pharaonic.dev/packages/laravel/api-response

Keep this file factual and package-specific. General Pharaonic engineering policy belongs in the private engineering repository, not here.
