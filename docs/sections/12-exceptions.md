## Exception Rendering

`ExceptionRenderer` renders the exceptions of API requests in the envelope. It is off until you register it with your exception handler, and it only renders: Laravel still reports every exception as before, once, following your `$dontReport` list.

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

A request is rendered when it expects JSON (`Accept: application/json`, or an AJAX request) or its path matches `exceptions.paths` (`api/*` by default). Web pages are left to Laravel. Unless a [mapper](#exceptions) is configured for it, the renderer also returns `null`, leaving the exception to Laravel, for exceptions that carry their own response:

- `HttpResponseException`;
- exceptions that implement `Responsable` or have a `render()` method, except HTTP exceptions with an error code (below);
- a `ValidationException` created with a response.

### Exceptions With an Error Code

An HTTP exception with a public `errorCode()` method sends that code, in upper case like every code of the envelope, in place of the status code, keeping its status, message and headers. An exception with a public `localizedMessage()` method sends that message, so it is translated by the package that owns it. It is rendered in the envelope even when it has a `render()` method, because the envelope carries everything that method sends, and for every request, even one that matches neither JSON nor `exceptions.paths`: such an exception only comes from an API, and its client may not send `Accept: application/json`. The exceptions of `pharaonic/laravel-api-versioning` and `pharaonic/laravel-api-idempotency` work this way, so they get `success`, the request ID and the response profile like every other error:

```json title="Response" no-copy
{
  "success": false,
  "message": "A request with this idempotency key is still being processed.",
  "code": "IDEMPOTENCY_IN_PROGRESS",
  "request_id": "9b1d…"
}
```

Their messages are translated by those packages from their own `api-versioning::messages` and `api-idempotency::messages` lines. Their codes are sent in upper case, so `idempotency_in_progress` becomes `IDEMPOTENCY_IN_PROGRESS`; compare codes without case if a client also reads that package's own response. An `errorCode()` that returns anything but letters, digits, `_`, `.`, `:` and `-` is ignored and the status code is sent.

### Built-in Mappings

| Exception | Status | Code | Message |
| --- | --- | --- | --- |
| `ValidationException` | its status (422) | `VALIDATION_ERROR` | its message, with `errors` from its bag |
| `AuthenticationException` | 401 | `UNAUTHENTICATED` | its message, or Unauthenticated. |
| `AuthorizationException` | 403 | `FORBIDDEN` | its message, or This action is unauthorized. |
| `ModelNotFoundException` | 404 | `NOT_FOUND` | Not Found. (the model name is sent only in debug mode) |
| `TokenMismatchException` | 419 | `PAGE_EXPIRED` | CSRF token mismatch. |
| any HTTP exception (404 route, 405, 429, `abort()`...) | its status | a fixed code per status from RFC 9110, e.g. `TOO_MANY_REQUESTS`, `CONTENT_TOO_LARGE`; 401 is `UNAUTHENTICATED`, 500 is `SERVER_ERROR`, an unlisted status is `HTTP_<status>` | its message, or the translated status text |
| anything else | 500 | `SERVER_ERROR` | Server Error. |

Codes never follow the framework's status text, so a status sends the same code on every Laravel line.

An HTTP exception keeps its headers, such as `Retry-After` and `X-RateLimit-*` from throttling, `Allow` from a 405, or a `WWW-Authenticate` challenge.

### Translated Messages

Every built-in message is translated in the application locale, from `api-response::messages`. English and Arabic ship with the package; a locale without a line falls back to English. The `code` never changes with the locale, so clients branch on the code and show the message.

| Key | English |
| --- | --- |
| `unauthenticated` | Unauthenticated. |
| `forbidden` | This action is unauthorized. |
| `not_found` | Not Found. |
| `validation` | The given data was invalid. |
| `too_many_attempts` | Too Many Attempts. |
| `page_expired` | CSRF token mismatch. |
| `server_error` | Server Error. |
| `status.<code>` | the status text of an HTTP exception without a message, e.g. `status.503` is Service Unavailable |

Laravel's own English messages (`Unauthenticated.`, `This action is unauthorized.`, `The given data was invalid.`, `Too Many Attempts.`) are translated too. Any other exception message is yours and is sent as it is, so translate it where you throw it: `abort(409, __('orders.exists'))`.

Publish the files to change a line or add a language:

```bash title="Terminal" no-line-numbers
php artisan vendor:publish --tag=api-response-translations
```

```php title="resources/lang/vendor/api-response/fr/messages.php"
return [
    'not_found' => 'Introuvable.',
    // ...
];
```

### Debug Details

With `app.debug` on, an unexpected exception adds `meta.debug` with its class, message, file, line and trace (file, line, class and function of each frame, without arguments). A `ModelNotFoundException` adds the same details plus `model`, the model class, and `ids`, the keys that were not found. With `app.debug` off, nothing about the exception is sent.

:::danger Keep app.debug off in production
`meta.debug` shows file paths and messages that can contain SQL or secrets. It follows `app.debug` only, so it is off whenever Laravel's own debug pages are.
:::

### Your Own Exceptions

Map a domain exception to a response with an `ExceptionMapper`:

```php title="app/Exceptions/InsufficientBalanceMapper.php"
namespace App\Exceptions;

use Illuminate\Http\Request;
use Pharaonic\Laravel\ApiResponse\Response;
use Pharaonic\Laravel\ApiResponse\Contracts\ExceptionMapper;
use Throwable;

class InsufficientBalanceMapper implements ExceptionMapper
{
    public function map(Throwable $exception, Request $request): ?Response
    {
        return Response::error('Insufficient balance.', 422)
            ->code('INSUFFICIENT_BALANCE')
            ->meta('balance', $exception->balance);
    }
}
```

```php title="config/pharaonic/api-response.php"
'exceptions' => [
    'paths' => ['api/*'],
    'mappers' => [
        App\Exceptions\InsufficientBalance::class => App\Exceptions\InsufficientBalanceMapper::class,
    ],
],
```

A mapper may be registered for a class, a parent class or an interface. The mapper of the exception's own class is asked first, then its parents from the closest, then its interfaces, then the built-in mappings. A mapper that returns `null` passes the exception on to the next one. A configured mapper is asked even for an exception that has its own `render()` method, so you can bring a third-party exception into the envelope.

:::tip Exceptions you own can render themselves
An exception class of your own can also return a `Pharaonic\Laravel\ApiResponse\Response` from its `render()` method. Laravel renders it directly and the renderer leaves it alone.
:::
