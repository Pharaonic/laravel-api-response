# Changelog

All notable changes to this project are documented here.

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
