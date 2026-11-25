## Methods & Classes

All classes are in the `Pharaonic\Laravel\ApiResponse` namespace. Classes under `Support` and the built-in adapters under `Pagination` are internal.

### Constructors

| Method | Description | Returns |
| --- | --- | --- |
| `Response::success(mixed $data = null)` | A 200 success; without an argument, no `data` field. | `Response` |
| `Response::created(mixed $data = null)` | A 201 success. | `Response` |
| `Response::accepted(mixed $data = null)` | A 202 success. | `Response` |
| `Response::noContent()` | A bodyless 204. | `Response` |
| `Response::error(string $message, int $status = 400)` | An error; the status must be 400 to 599. | `Response` |
| `Response::unauthorized(?string $message = null)` | A 401 with code `UNAUTHENTICATED`; without a message, `api-response::messages.unauthenticated`. | `Response` |
| `Response::forbidden(?string $message = null)` | A 403 with code `FORBIDDEN`; without a message, `api-response::messages.forbidden`. | `Response` |
| `Response::notFound(?string $message = null)` | A 404 with code `NOT_FOUND`; without a message, `api-response::messages.not_found`. | `Response` |
| `Response::validationError(array $errors, ?string $message = null)` | A 422 with code `VALIDATION_ERROR` and the errors; without a message, `api-response::messages.validation`. | `Response` |

### Fluent Methods

| Method | Description | Returns |
| --- | --- | --- |
| `data(mixed $data)` | Sets the data, `null` included; refused on an error. | `static` |
| `message(?string $message)` | Sets or removes the message. | `static` |
| `status(int $status)` | Sets the status; a success stays below 400 and an error from 400. | `static` |
| `code(?string $code)` | Sets or removes the application error code. | `static` |
| `errors(?array $errors)` | Sets or removes the errors; refused on a success. | `static` |
| `meta(string\|array $key, mixed $value = null)` | Adds metadata; `pagination` is reserved. | `static` |
| `notify(string $message, ?string $type = null, ?string $title = null, ?int $duration = null, array $data = [])` | Adds a notification. | `static` |
| `profile(string $name)` | Chooses a registered profile. | `static` |
| `withSEO()` | Adds the SEO tags of `pharaonic/laravel-seo`. | `static` |
| `withRequestId()` / `withoutRequestId()` | Adds or leaves out the request ID in the body. | `static` |
| `withQueryString(?array $only = null)` | Keeps every allowed query key, or only the given keys, in pagination links. | `static` |
| `withoutQueryString()` | Keeps only the page or cursor parameter in pagination links. | `static` |
| `withoutPagination()` | Sends a paginator's items without `meta.pagination`. | `static` |
| `header(string $name, string $value)` | Adds a header. | `static` |
| `withHeaders(array $headers)` | Adds several headers. | `static` |
| `when(mixed $value, callable $callback, ?callable $default = null)` | Runs the callback when the value is truthy. | `static` |
| `unless(mixed $value, callable $callback, ?callable $default = null)` | Runs the callback when the value is falsy. | `static` |
| `transform(callable $callback)` | Adds a payload transform. | `static` |
| `response()` | Builds the response for the current request. | `Symfony\Component\HttpFoundation\Response` |
| `toResponse($request)` | Builds the response for the given request (`Responsable`). | `Symfony\Component\HttpFoundation\Response` |

### Macros

| Method | Description | Returns |
| --- | --- | --- |
| `Response::macro(string $name, object\|callable $macro)` | Registers a macro; an existing method name is refused. | `void` |
| `Response::hasMacro(string $name)` | Whether the macro is registered. | `bool` |
| `Response::flushMacros()` | Forgets every macro. | `void` |

### Classes

| Class | Description |
| --- | --- |
| `Profiles\Profile` | Base class of every profile; see [Profiles](#profiles) for its methods. |
| `Contracts\PaginationAdapter` | `supports(object $paginator): bool`, `items(object $paginator): mixed`, `meta(object $paginator, Closure $url): array`. |
| `Contracts\ExceptionMapper` | `map(Throwable $exception, Request $request): ?Response`. |
| `ExceptionRenderer` | Invokable: `(Throwable $exception, Request $request): ?Response`. |
| `Http\Middleware\AssignRequestId` | Assigns, returns and logs the request ID. |
| `Http\Middleware\UseResponseProfile` | Chooses the profile of a route: `UseResponseProfile::class.':name'`. |

### Exceptions Thrown

| Exception | When |
| --- | --- |
| `InvalidArgumentException` | An invalid status, code, header, notification, profile name, macro name or configuration value. |
| `LogicException` | `withSEO()` without `pharaonic/laravel-seo` 6.1 or later, while `seo.missing_driver` is `throw`. |
| `JsonException` | The body cannot be encoded as JSON. |
| `UnexpectedValueException` | A custom request ID generator returned an invalid ID. |
