---
name: api-response-development
description: Building Laravel API responses with pharaonic/laravel-api-response (Response::success() and error(), status, errors, meta, shared metadata, notifications, profiles, resources and the three paginators with query-string control, the exception renderer, request IDs and withSEO()). Use when writing or changing an API controller, an API error, pagination output, a response profile, or tests of API JSON.
---

# Laravel API Response Development

## When to use this skill

Use this skill when you return JSON from an API controller, shape an error, paginate a list, add metadata or notifications, choose or write a response profile, register the exception renderer or the request ID middleware, or test the JSON an endpoint sends.

## Setup

The package is auto-discovered. Publishing is optional:

```bash
php artisan vendor:publish --tag=api-response-config        # config/pharaonic/api-response.php
php artisan vendor:publish --tag=api-response-translations  # lang/vendor/api-response
```

```php
'default_profile' => 'default',
'profiles' => ['default' => Pharaonic\Laravel\ApiResponse\Profile::class],
'pagination' => [
    'query_string' => true, // keep the request's query string in pagination links
    'sensitive_query' => ['token', 'api_key', 'access_token', 'password'], // never copied into links
],
'exceptions' => ['paths' => ['api/*']], // rendered besides requests that expect JSON
'request_id' => ['header' => 'X-Request-ID', 'trust_incoming' => false, 'logging' => true],
```

Register the optional pieces in the application:

```php
// app/Exceptions/Handler.php
public function register(): void
{
    $this->renderable(new \Pharaonic\Laravel\ApiResponse\ExceptionRenderer);
}

// app/Http/Kernel.php, first in the 'api' group
\Pharaonic\Laravel\ApiResponse\Http\Middleware\AssignRequestId::class,
```

## Features

### Building a response

`Pharaonic\Laravel\ApiResponse\Response` (final). Start with a factory, chain in any order, return it.

| Call | Does |
| --- | --- |
| `Response::success(mixed $data = null)` | 200 success. `data` is always sent as given (`null`, `[]`, `0` included). Models, collections, `Arrayable`, `Jsonable`, resources and paginators work. |
| `Response::error(string $message, int $status = 400)` | Error; status 400–599. |
| `->message(?string)` | Sets `message` (sent as `null` when not set). |
| `->status(int)` | 2xx for a success, 4xx/5xx for an error; 204 and 205 send no body. A wrong status throws `InvalidArgumentException`. |
| `->errors(?array)` | Error details, e.g. `['email' => ['...']]`. Throws on a success. |
| `->meta(string\|array $key, mixed $value = null)` | Adds metadata; later values win. `pagination` is reserved. |
| `Response::shareMeta(string\|array $key, mixed $value = null)` | Adds metadata to every response of the current request (call it in a middleware). Stored on the request. |
| `->notify(string $message, string $type = 'info')` | Appends `{type, message}`; type is `success`, `info`, `warning` or `error`. |
| `->profile(string $name)` | Uses a profile registered in `pharaonic.api-response.profiles`. |
| `->withRequestId()` | Adds `request_id` even when the profile leaves it out. |
| `->withQueryString(?array $only = null)` / `->withoutQueryString()` | Which query keys pagination links keep; the page or cursor parameter is always kept. |
| `->withSEO()` | Adds `seo` from pharaonic/laravel-seo (`seo()->toArray()`). Throws `LogicException` when the package is missing. |
| `->response()` / `->toResponse($request)` | Builds the concrete response, e.g. to add a header. |

Body order: `success`, `message`, `data` or `errors`, `meta`, then `notifications`, `seo`, `request_id` when present. Metadata merges `shareMeta()`, a resource's `with()`/`additional()`, `meta()`, then `pagination`.

### Pagination

```json
{"type": "length_aware", "pages": {"current": 2, "last": 4, "per_page": 15, "has_more": true},
 "items": {"total": 52, "from": 16, "to": 30}, "links": {"first": "…", "last": "…", "prev": "…", "next": "…"}}
{"type": "simple", "pages": {"current": 1, "per_page": 15, "has_more": true}, "items": {"from": 1, "to": 15}, "links": {"prev": null, "next": "…"}}
{"type": "cursor", "pages": {"per_page": 15, "has_more": true}, "cursors": {"current": null, "prev": null, "next": "eyJ…"}, "links": {"prev": null, "next": "…"}}
```

A resource collection over a paginator sends the transformed items once (never `data.data`).

### Profiles

```php
namespace App\Http\Profiles;

use Pharaonic\Laravel\ApiResponse\Profile;

class MobileProfile extends Profile
{
    public function fields(): array { return ['data' => 'result']; } // only renamed fields
    public function requestIdInBody(): bool { return false; }
}
// config: 'profiles' => ['default' => Profile::class, 'mobile' => MobileProfile::class]
```

Renamable fields: `success`, `message`, `data`, `errors`, `meta`, `notifications`, `seo`, `request_id`. Profiles are built by the container. A profile never adds SEO.

### Exception renderer

For requests that expect JSON or match `exceptions.paths`: `ValidationException` → its status (422) with `errors`; `AuthenticationException` 401; `AuthorizationException` 403; `ModelNotFoundException` 404 "Not Found." (never the model name); `TokenMismatchException` 419; any HTTP exception → its status, message and headers (`Retry-After`, `Allow`, `WWW-Authenticate`); anything else 500 "Server Error." with `app.debug` off. Exceptions with `render()` or their own response, web pages, and unexpected exceptions with `app.debug` on are left to Laravel. Messages are translated from `api-response::messages` (English and Arabic shipped).

### Request IDs

`AssignRequestId` sets a UUID (or a valid incoming ID when `trust_incoming` is on: 1–128 of `A-Za-z0-9._:-`), sends it in `X-Request-ID`, adds `request_id` to the body (per profile) and `extra.request_id` to log records of the default channel.

## Pitfalls

- Do not return `response()->json([...])` next to this package in the same API; build every response with `Response` so clients get one shape.
- Do not call `meta('pagination', ...)` or `shareMeta('pagination', ...)`; it throws.
- `success()->status(404)` and `error(...)->status(200)` throw; use the other factory.
- `withSEO()` needs pharaonic/laravel-seo installed; do not call it "just in case".
- A profile name that is not in the config throws when the response is built; register it first.
- Translate your own exception messages where you throw them (`abort(409, __('orders.exists'))`); only the package's and Laravel's own English messages are translated for you.

## Testing

Assert the JSON with Laravel's HTTP tests:

```php
$this->getJson('/api/posts/1')
    ->assertOk()
    ->assertJson(['success' => true, 'data' => ['id' => 1], 'meta' => null]);

$this->postJson('/api/posts', [])
    ->assertStatus(422)
    ->assertJsonPath('success', false)
    ->assertJsonStructure(['message', 'errors' => ['title'], 'meta']);

$this->getJson('/api/posts?page=2')->assertJsonPath('meta.pagination.pages.current', 2);
```

To build a response without a route, call `$response->toResponse($request)` and decode `getContent()`.
