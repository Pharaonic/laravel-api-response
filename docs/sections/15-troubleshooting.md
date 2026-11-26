## Troubleshooting

### My exceptions still render as HTML

The renderer is not registered, or the request is not an API request. Register it in `withExceptions()` in `bootstrap/app.php` ([Exception Rendering](#exceptions)), and send `Accept: application/json` or add the path to `exceptions.paths`.

### A 500 shows Laravel's debug output instead of the envelope

That happens while `app.debug` is on, so you can see the exception. With `app.debug` off, a 500 sends "Server Error." in the envelope.

### An exception keeps its own JSON

Exceptions with a `render()` method or their own response are left to Laravel. Return a `Response` from that `render()` method if you want the envelope.

### InvalidArgumentException: The response profile [...] is not defined

The profile name passed to `profile()`, or `default_profile`, is not a key of `pharaonic.api-response.profiles`. If you published the configuration before adding a profile, add it to the published file.

### There is no request_id in the body

The request did not pass through `AssignRequestId`, or the profile's `requestIdInBody()` returns `false`. Add the middleware, or call `withRequestId()` on that response.

### Pagination links lose a query parameter

The key is in `pagination.sensitive_query`, the response calls `withQueryString([...])` without it, or `pagination.query_string` is `false`.

### LogicException: withSEO() needs pharaonic/laravel-seo

Install it with `composer require pharaonic/laravel-seo` (12.1 or later), or remove the `withSEO()` call.
