## Laravel API Response (pharaonic/laravel-api-response)

One JSON envelope for Laravel API responses: `Response::success()` and `Response::error()` resolve data, API resources and every paginator, with profiles, metadata, notifications, request IDs, optional SEO tags and an opt-in exception renderer. Use the `api-response-development` skill for the full API.

### Conventions

- Import `Pharaonic\Laravel\ApiResponse\Response` (Laravel has its own `Response` facade; alias it `as ApiResponse` where both are used). Return the builder from the controller; it is `Responsable`, so do not call `response()` unless you need the concrete response.
- A success is `{success, message, data, meta}` with a 2xx status; an error is `{success, message, code, errors, meta}`, with `code` set by `->code('auth.expired')` and `null` until then, with a 4xx or 5xx status. `meta` is `null` while empty. 204 and 205 have no body.
- Never wrap data yourself: pass the model, the resource or the paginator. Paginators put their metadata in `meta.pagination` (`type`, `pages`, `items` or `cursors`, `links`); `pagination` is reserved in `meta()`.
- `notify()` takes its optional parts by name (`title`, `code`, `action` as `['label' => ..., 'url' => ...]`, `duration` in milliseconds, `position`); each is sent only when given.
- Metadata that every response of a request needs comes from `Response::shareMeta()` in a middleware, never from static state.
- Exception rendering and request IDs are off until the application registers `ExceptionRenderer` in its handler and the `AssignRequestId` middleware.

### Main features

@verbatim
<code-snippet name="Success and error responses" lang="php">
use Pharaonic\Laravel\ApiResponse\Response;

return Response::success(new PostResource($post))
    ->status(201)
    ->message('Post created.')
    ->meta('version', '1.0')
    ->notify('Your post is live.', 'success', title: 'Published', code: 'post.published');

return Response::error('Validation failed.', 422)
    ->errors(['email' => ['The email field is required.']]);
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Paginators and query strings" lang="php">
return Response::success(PostResource::collection(Post::query()->paginate(15)))
    ->withQueryString(['search', 'sort']); // or withoutQueryString()
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Exception rendering" lang="php">
use Pharaonic\Laravel\ApiResponse\ExceptionRenderer;

// Laravel 11 and later, bootstrap/app.php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(new ExceptionRenderer);
})

// Laravel 10, register() of app/Exceptions/Handler.php
$this->renderable(new ExceptionRenderer);
</code-snippet>
@endverbatim
