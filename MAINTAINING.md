# Maintaining This Package

This file records package-specific maintainer facts that are safe to keep in the public repository.

## Purpose

Consistent Laravel API responses: one `Response` builder for success and error envelopes, resolving resources and Laravel's three paginators, with profile classes, notifications, request IDs, optional SEO tags, and opt-in exception rendering. 6.1.0 replaced the larger 6.0 design on purpose; keep the surface this small.

## Supported Laravel lines

One branch per Laravel major, from `6.x` to the newest major, all carrying the same code. Each line's PHP range, `illuminate/*` constraint and tools follow the Pharaonic Laravel line table. `6.x` runs PHPUnit, because Pest cannot install on Laravel 6; later lines run Pest. `develop` follows the newest line.

Per-line differences beyond constraints:

- Translations ship from 7.x (decided 2026-11-26): `resources/lang/{en,ar}/messages.php` under `api-response::messages`, published with the `api-response-translations` tag, to `resourcePath('lang/vendor/api-response')` on 7.x–8.x and `lang_path('vendor/api-response')` from 9.x. 6.x has none and its messages go through the application's JSON translations; 6.x was released without them and stays that way.
- Laravel Boost resources ship from 10.x (`resources/boost`), as `LARAVEL.md` requires; the lines below have none.
- From 10.x, `meta.pagination` is grouped: `type`, `pages`, `items` or `cursors`, `links` (decided 2026-11-26). 6.x–9.x keep the flat shape.
- From 10.1, `notify()` also takes `title`, `code`, `action`, `duration` and `position` (decided 2026-11-26). 6.x–9.x keep `{type, message}`.

- Cursor paginators exist from Laravel 8.41. `Support\Pagination` names their contract by string, so it does nothing below; the cursor test skips there.
- The exception renderer is registered differently per line, and the docs of each line show its own way: `render()` of the application handler on 6.x–7.x, `$this->renderable()` on 8.x–10.x, `withExceptions()` in `bootstrap/app.php` from 11.x. From 8.x Laravel passes callbacks the prepared exception (`ModelNotFoundException` and `AuthorizationException` become HTTP exceptions), so the renderer handles both forms.

## Core library

None. Everything here is Laravel glue: responses, resources, paginators, exception rendering and middleware.

## Public API invariants

Configuration keys (`pharaonic.api-response.*`), the publish tags, and the translation keys of `api-response::messages` are public API.

- Public: `Response` (`success()`, `error()` and its fluent methods), `Profile` and its overridable `fields()` and `requestIdInBody()`, `ExceptionRenderer`, `Http\Middleware\AssignRequestId`. Everything under `Support` is `@internal`. There is no facade, helper, macro or middleware alias: no Pharaonic facade convention exists yet, and static factories give the same call site.
- The envelope: `success`, `message`, `data` (success) or `errors` (error), and `meta` (`null` while empty), always sent; then `notifications`, `seo`, `request_id` only when present, in that order. A notification is `type`, `title`, `message`, `code`, `action` (`label`, `url`), `duration`, `position` in that order, each optional key only when given. The names and order of `notify()`'s parameters are public API, since callers pass them by name or position: a new part is added last. Metadata merges `shareMeta()`, then resource `with()`/`additional()`, then `meta()`, then the package's `pagination`. `meta.pagination` and its keys per `type` (`length_aware`, `simple`, `cursor`).
- A success keeps a 2xx status and an error a 4xx or 5xx one; 204 and 205 have no body.
- Shared metadata lives in the request attribute `pharaonic.api-response.meta`, never in static state.
- The request attribute `pharaonic.api-response.request_id`, and `extra.request_id` on log records.
- No SEO field without `withSEO()` on that response; the integration reads only `seo()->toArray()` through the `pharaonic-seo` binding and names no laravel-seo class.

## Package-specific constraints

- No request state outside the request: builders are plain objects, the request ID lives in a request attribute, and configuration is read when a response is built. The log processor reads the current request when a record is written and is pushed once per Monolog instance (found among its processors), so a long-running worker never logs another request's ID.
- Building reads the builder's state and never changes it, so a builder can be built twice.
- The request ID is always `Str::uuid()`: `Str::orderedUuid()` needs `moontoast/math` with ramsey/uuid 3 (Laravel 6–7).
- `pharaonic/laravel-seo` is in `suggest` only; the CI `integrations` job installs it and runs the tests.

## Branch notes

Fixes land on the oldest affected line and are ported to every later line and `develop`.

## Documentation

https://pharaonic.dev/packages/laravel/api-response

Keep this file factual and package-specific. General Pharaonic engineering policy belongs in the private engineering repository, not here.
