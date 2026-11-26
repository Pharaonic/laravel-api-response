## Exception Rendering

`ExceptionRenderer` renders the exceptions of API requests in the error envelope. It is off until you register it, and it only renders: Laravel still reports every exception, once, following your `$dontReport` list.

### Registering the Renderer

On Laravel 7, call it from `render()` in your application's handler, and let Laravel render anything it returns `null` for:

```php title="app/Exceptions/Handler.php"
use Pharaonic\Laravel\ApiResponse\ExceptionRenderer;
use Throwable;

public function render($request, Throwable $exception)
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

### Translated Messages

The package's messages follow the application locale, from `api-response::messages`. English and Arabic ship with the package; a locale without a line falls back to English.

| Key | English |
| --- | --- |
| `server_error` | Server Error. |
| `not_found` | Not Found. |
| `unauthenticated` | Unauthenticated. |
| `forbidden` | This action is unauthorized. |
| `page_expired` | CSRF token mismatch. |
| `too_many_attempts` | Too Many Attempts. |
| `validation` | The given data was invalid. |
| `status.<code>` | the status text of an HTTP exception without a message, e.g. `status.503` is Service Unavailable |

Laravel's own English messages (Unauthenticated., This action is unauthorized., The given data was invalid., Too Many Attempts., CSRF token mismatch.) are translated through the same lines. Any other exception message is yours and is sent as it is, so translate it where you throw: `abort(409, __('orders.exists'))`.

Publish the files to change a line or add a language:

```bash title="Terminal" no-line-numbers
php artisan vendor:publish --tag=api-response-translations
```

```php title="resources/lang/vendor/api-response/fr/messages.php"
return [
    'server_error' => 'Erreur du serveur.',
    'not_found' => 'Introuvable.',
    // ...
];
```
