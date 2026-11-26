## Methods & Classes

All classes are in the `Pharaonic\Laravel\ApiResponse` namespace. Classes under `Support` are internal.

### Response

| Method | Description | Returns |
| --- | --- | --- |
| `Response::success(mixed $data = null)` | A 200 success carrying the data. | `Response` |
| `Response::error(string $message, int $status = 400)` | An error; the status must be 400 to 599. | `Response` |
| `Response::shareMeta(string\|array $key, mixed $value = null)` | Adds metadata to every response of the current request, such as from a middleware. `pagination` is reserved. | `void` |
| `message(?string $message)` | Sets the message. | `Response` |
| `status(int $status)` | Sets the status: 2xx for a success, 4xx or 5xx for an error. 204 and 205 have no body. | `Response` |
| `code(?string $code)` | Sets the code of an error, sent as `null` until set; refused on a success. | `Response` |
| `errors(?array $errors)` | Sets the errors of an error; refused on a success. | `Response` |
| `meta(string\|array $key, mixed $value = null)` | Adds metadata: one key and value, or an array of them. `pagination` is reserved. | `Response` |
| `notify(string $message, string $type = 'info', ?string $title = null, ?string $code = null, ?array $action = null, ?int $duration = null, ?string $position = null)` | Adds a notification: `success`, `info`, `warning` or `error`, with an optional title, code, action (`label` and `url`), duration in milliseconds and position. | `Response` |
| `profile(string $name)` | Uses a profile registered in `pharaonic.api-response.profiles`. | `Response` |
| `withSEO()` | Adds the tags of pharaonic/laravel-seo. | `Response` |
| `withRequestId()` | Adds the request ID to the body even when the profile leaves it out. | `Response` |
| `withQueryString(?array $only = null)` | Keeps every query key, or only the given ones, in pagination links. | `Response` |
| `withoutQueryString()` | Keeps only the page or cursor parameter in pagination links. | `Response` |
| `response()` | Builds the HTTP response for the current request. | `Symfony\Component\HttpFoundation\Response` |
| `toResponse($request)` | Builds the HTTP response for the given request (`Responsable`). | `Symfony\Component\HttpFoundation\Response` |

### Other Classes

| Class | Description |
| --- | --- |
| `Profile` | The base profile: `fields()` returns the renamed fields (`[]`), `requestIdInBody()` whether the body carries the request ID (`true`). Extend it for your own profiles. |
| `ExceptionRenderer` | Invokable: `(new ExceptionRenderer)($exception, $request)` returns the error response of an API request, or `null` to leave the exception to Laravel. |
| `Http\Middleware\AssignRequestId` | Assigns the request ID, sends it in the configured header and adds it to log records. |
| `ApiResponseServiceProvider` | Merges and publishes the configuration (`api-response-config`), loads and publishes the translations (`api-response-translations`). Registered automatically. |

### Exceptions Thrown

| Exception | When |
| --- | --- |
| `InvalidArgumentException` | A status outside the allowed range, `errors()` or `code()` on a success, `meta('pagination', ...)` or `shareMeta('pagination', ...)`, an unknown notification type or position, a notification action without exactly a `label` and `url`, a negative notification duration, an unknown profile, a profile class that does not extend `Profile`, or an invalid profile field. |
| `LogicException` | `withSEO()` without pharaonic/laravel-seo. |
