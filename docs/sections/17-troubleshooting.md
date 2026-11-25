## Troubleshooting

### My exceptions are still rendered as HTML

The renderer only runs once you call it from your exception handler; see [Exception Rendering](#exceptions). It then renders requests that expect JSON or match `exceptions.paths`. Send `Accept: application/json`, or add your API's path to `exceptions.paths`.

### An exception keeps Laravel's own JSON shape

Exceptions that carry their own response are left to Laravel: `HttpResponseException`, exceptions with a `render()` method or that implement `Responsable`, and a `ValidationException` created with a response. Return a `Response` from that `render()` method, or map the exception with an `ExceptionMapper`.

### InvalidArgumentException: A success response needs a status below 400

`status()` refuses an error status on a success, and the other way round, so the `success` flag never contradicts the status. Start the response with `Response::error()` instead.

### InvalidArgumentException: An error response cannot carry data

An error carries its details in `errors()` or `meta()`, and a success never carries `errors()`. See [What Each Response Carries](#status-helpers).

### The data field is missing

`Response::success()` called without an argument sends no `data`. Pass the value, even `null`, or call `data()`.

### Pagination links lose a query parameter

Check `pagination.query_string.except`: denied keys are removed even when an allowlist names them. Check also whether the response calls `withoutQueryString()`, and whether `pagination.query_string.enabled` is `false`.

### Cursor paginators are sent as plain data

`cursorPaginate()` exists from Laravel 8.41, so it is supported on the 8.x line of this package and later.

### LogicException: withSEO() needs pharaonic/laravel-seo

Install `pharaonic/laravel-seo` 6.1 or later, or set `seo.missing_driver` to `ignore` to leave the field out.

### request_id is missing from the body

The request has no ID unless `AssignRequestId` ran for it. Register the middleware, and check `request_id.include_in_body`, the profile's `includesRequestId()` and `withoutRequestId()`.

### The client's X-Request-ID is not used

Incoming IDs are ignored unless `request_id.trust_incoming` is `true`, and even then an ID longer than `request_id.max_length` or with characters other than letters, digits, `.`, `_`, `:` and `-` is replaced.

### JsonException: Malformed UTF-8 characters

The data holds text that is not valid UTF-8, such as a binary column. Convert it or leave it out; the package never sends a partial body instead.

### A configuration change has no effect

Run `php artisan config:clear` after editing the published file when the configuration is cached.
