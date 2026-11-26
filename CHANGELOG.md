# Changelog

All notable changes to this project are documented here.

## 9.0.0 - 2026-11-26

### Compatibility

- Laravel 9.33+ on PHP 8.0, 8.1 and 8.2. 9.33 is the first 9.x release that requires a Carbon release working on PHP 8.2.
- The same API and output as 8.0.0. A validation error's message is its first error, as Laravel 9 sets it, instead of "The given data was invalid.".
- Translations are published to `lang/vendor/api-response`.
- `withSEO()` needs pharaonic/laravel-seo 9.1 or later.

## 8.0.0 - 2026-11-26

### Fixed

- A `TokenMismatchException` sends "CSRF token mismatch." (translated) after Laravel 8 turns it into a 419 HTTP exception, instead of the "Page Expired" status text.
- Only an HTTP exception is read through its previous exception, so an unrelated exception that wraps a `ModelNotFoundException` sends a 500, not a 404.

### Compatibility

- Laravel 8.75+ on PHP 8.0 and 8.1.
- The same API and output as 7.0.0.
- The exception renderer is registered with `$this->renderable(new ExceptionRenderer)` in the handler's `register()`.
- `withSEO()` needs pharaonic/laravel-seo 8.1 or later.
- Cursor paginators are covered by the tests.

## 7.0.0 - 2026-11-26

### Added

- Translated messages in the `api-response` namespace, English and Arabic, published with the `api-response-translations` tag. The renderer's messages (server error, not found, unauthenticated, forbidden, CSRF, the status text of an HTTP exception without a message) and Laravel's own English messages follow the application locale; any other exception message is sent as it is.

### Behavior Changes from 6.1.x

- The renderer's messages come from `api-response::messages` instead of the application's JSON translations: move a line such as `"Server Error."` in `lang/<locale>.json` to `resources/lang/vendor/api-response/<locale>/messages.php`.
- The `LogicException` of `withSEO()` without pharaonic/laravel-seo no longer names a release.

### Compatibility

- Laravel 7.30+ on PHP 8.0.
- The same API and output as 6.1.0, apart from the messages above.
- `withSEO()` needs pharaonic/laravel-seo 7.1 or later.
- The tests run on Pest.

## 6.1.0 - 2026-11-26

A redesign around a smaller API. It breaks 6.0 code on purpose: 6.0.0 had been out for a day, and its surface was much larger than an API envelope needs.

### Behavior Changes from 6.0.x

- `Response` starts a response only with `success()` and `error()`, and `Response::shareMeta()` adds metadata to every response of the current request, such as from a middleware. `created()`, `accepted()`, `noContent()`, `unauthorized()`, `forbidden()`, `notFound()` and `validationError()` are removed; use `status()` and `errors()`.
- The fluent methods are `message()`, `status()`, `errors()`, `notify()`, `profile()`, `withSEO()`, `withRequestId()`, `withQueryString()`, `withoutQueryString()`, `meta()`, `response()` and `toResponse()`. `data()`, `code()`, `header()`, `withHeaders()`, `withoutRequestId()`, `withoutPagination()`, `when()`, `unless()`, `transform()`, `macro()` and `flushMacros()` are removed, and the class is final.
- The envelope always sends `message`, `data` on a success or `errors` on an error, and `meta`, `null` included (`meta` is `null` while empty). The `code` field and the built-in error codes are removed.
- A success needs a 2xx status (3xx is refused). 205 is bodyless like 204. JSON is encoded with Laravel's default `JsonResponse` options, so the `json_options` setting is removed.
- `meta.pagination` is flat: `type`, then `current_page`, `last_page`, `per_page`, `total`, `from`, `to`, `has_more_pages` (length-aware), the same without `total` and `last_page` (simple), or `per_page`, `next_cursor`, `prev_cursor`, `has_more_pages` (cursor), then `links`. `paginationInformation()` of resource collections is no longer read. `pagination.enabled`, `pagination.adapters` and `Contracts\PaginationAdapter` are removed, and `pagination.query_string.except` is now `pagination.sensitive_query`.
- A profile is now a class extending `Profile` (was `Profiles\Profile`) with only `fields()`, which returns just the renamed fields, and `requestIdInBody()`; `body()`, `pagination()`, `notification()`, `headers()`, `includesRequestId()` and `paginates()` are removed. The `UseResponseProfile` middleware, content negotiation and `envelope.fields` are removed.
- Notifications are `{type, message}`; `notify()` takes only a message and a type (`success`, `info`, `warning` or `error`, `info` by default). The `notifications` settings are removed.
- `ExceptionRenderer` has no mappers (`exceptions.mappers` and `Contracts\ExceptionMapper` are removed), no longer reads `errorCode()` or `localizedMessage()`, and leaves exceptions with a `render()` method to Laravel. With `app.debug` on, an unexpected exception is left to Laravel's debug output instead of `meta.debug`. A `ModelNotFoundException` Laravel has turned into a 404 never sends the model name.
- The `api-response` translations are removed. The package's own messages go through the application's JSON translations (`__('Server Error.')`).
- `AssignRequestId` always generates a UUID; `request_id.generator`, `request_id.max_length` (now fixed at 128) and `request_id.include_in_body` (now `requestIdInBody()` per profile) are removed.
- `withSEO()` without pharaonic/laravel-seo always throws; `seo.missing_driver` is removed.

### Compatibility

- Laravel 6 on PHP 8.0.

## 6.0.0 - 2026-11-25

### Added

- `Response`: `success()`, `created()`, `accepted()`, `noContent()`, `error()`, `unauthorized()`, `forbidden()`, `notFound()` and `validationError()`, with `data()`, `message()`, `status()`, `code()`, `errors()`, `meta()`, `notify()`, `profile()`, `withSEO()`, `withRequestId()`, `withoutRequestId()`, `withQueryString()`, `withoutQueryString()`, `withoutPagination()`, `header()`, `withHeaders()`, `when()`, `unless()`, `transform()` and `response()`. It is `Responsable` and macroable. An error refuses `data()` and a success refuses `errors()`.
- Length-aware and simple paginators, resource collections over them, and `Contracts\PaginationAdapter` for other paginators, sent as their items plus `meta.pagination`. Cursor paginators are supported from Laravel 8.41.
- Profiles (`Profiles\Profile`), chosen by the response, the `UseResponseProfile` middleware, the `Accept` media type, or the default, and configurable envelope field names.
- `ExceptionRenderer` and `Contracts\ExceptionMapper` for opt-in JSON rendering of exceptions on API requests. HTTP exceptions get a fixed code per status (RFC 9110 names, `HTTP_<status>` otherwise), or the code of their `errorCode()` method in upper case and the message of their `localizedMessage()` method, as the exceptions of `pharaonic/laravel-api-versioning` and `pharaonic/laravel-api-idempotency` have; those are rendered in the envelope even with a `render()` method, and for every request. Configured mappers are asked before an exception's own `render()`. With `app.debug` on, a `ModelNotFoundException` adds the model class and the missing keys to `meta.debug`.
- Translated messages in the `api-response` namespace, English and Arabic, published with the `api-response-translations` tag. The status helpers' defaults and every built-in exception message follow the application locale; codes do not.
- The `AssignRequestId` middleware, with the ID in a header, the body and log records.
- Optional `pharaonic/laravel-seo` (6.1 or later) tags through `withSEO()`.

### Compatibility

- Laravel 6 on PHP 8.0.
