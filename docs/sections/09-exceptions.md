## Exception Rendering

`ExceptionRenderer` renders the exceptions of API requests in the error envelope. It is off until you register it, and it only renders: Laravel still reports every exception, once, following your `$dontReport` list.

### Registering the Renderer

On Laravel 6, call it from `render()` in your application's handler, and let Laravel render anything it returns `null` for:

```php title="app/Exceptions/Handler.php"
use Exception;
use Pharaonic\Laravel\ApiResponse\ExceptionRenderer;

public function render($request, Exception $exception)
{
    return (new ExceptionRenderer)($exception, $request) ?? parent::render($request, $exception);
}
```

### Which Requests It Renders

A request is rendered when it expects JSON (`Accept: application/json`, or an AJAX request) or its path matches `exceptions.paths` (`api/*` by default). Web pages keep Laravel's own rendering.

The renderer also returns `null`, leaving the exception to Laravel, for:

- exceptions that carry their own response: `HttpResponseException`, `Responsable` exceptions, exceptions with a `render()` method, and a `ValidationException` created with a response;
- an unexpected exception while `app.debug` is on, so you still get Laravel's debug output.

### Mappings

| Exception | Status | Message |
| --- | --- | --- |
| `ValidationException` | its status (422) | its message, with `errors` from its bag |
| `AuthenticationException` | 401 | its message, or Unauthenticated. |
| `AuthorizationException` | 403 | its message, or This action is unauthorized. |
| `ModelNotFoundException` | 404 | Not Found. |
| `TokenMismatchException` | 419 | CSRF token mismatch. |
| any HTTP exception (missing route, 405, 429 throttling, `abort()`) | its status | its message, or the status text |
| anything else, `app.debug` off | 500 | Server Error. |

```json title="Response" no-copy
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {"email": ["The email must be a valid email address."]},
  "meta": null
}
```

An HTTP exception keeps its headers, such as `Retry-After` and `X-RateLimit-*` from throttling, `Allow` from a 405, or a `WWW-Authenticate` challenge. When the [`AssignRequestId` middleware](#request-ids) runs, the error carries the request ID in its header and body like any other response, and metadata shared with `Response::shareMeta()` joins its `meta`.

:::danger Nothing about a server error is sent
A 500 sends only "Server Error.": no exception class, message, file, trace or SQL. A missing model sends only "Not Found.", never the model class or the keys.
:::

### Translating the Messages

The package's own messages (Server Error., Not Found., Unauthenticated., This action is unauthorized., CSRF token mismatch., and the status texts) go through Laravel's translator, so a line in your JSON translation file replaces them:

```json title="resources/lang/fr.json"
{
    "Server Error.": "Erreur du serveur.",
    "Not Found.": "Introuvable."
}
```

Messages that come from an exception are sent as they are, so translate them where you throw: `abort(409, __('orders.exists'))`.
