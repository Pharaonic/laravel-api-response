## Basic Usage

`Response::success()` and `Response::error()` start a response. Return it from a controller: it implements Laravel's `Responsable`, so Laravel builds the JSON response when it sends it.

### Success

:::tip Import the package's Response
Laravel has its own `Response` facade, so import `Pharaonic\Laravel\ApiResponse\Response` in each controller, or alias it (`use Pharaonic\Laravel\ApiResponse\Response as ApiResponse;`) where you also use Laravel's.
:::

```php title="app/Http/Controllers/PostController.php"
use App\Models\Post;
use Pharaonic\Laravel\ApiResponse\Response;

public function show(Post $post)
{
    return Response::success($post);
}
```

```json title="Response" no-copy
{
  "success": true,
  "message": null,
  "data": {"id": 1, "title": "Hello"},
  "meta": null
}
```

`data` and `meta` are always sent; `data` as you set it: `null`, `[]`, `false` and `0` stay as they are. A model, a collection, an `Arrayable`, a `Jsonable` or any JSON-serializable value works, as do [API resources](#api-resources) and [paginators](#pagination).

### Message and Status

Every modifier returns the response, so you can chain them in any order:

```php title="app/Http/Controllers/PostController.php"
return Response::success($post)
    ->message('Post created.')
    ->status(201)
    ->notify('Post created.', 'success');
```

A success needs a 2xx status. `204` and `205` are sent without a body:

```php title="app/Http/Controllers/PostController.php"
$post->delete();

return Response::success()->status(204);
```

### Errors

```php title="app/Http/Controllers/PostController.php"
return Response::error('Post not found.', 404);
```

```json title="Response" no-copy
{
  "success": false,
  "message": "Post not found.",
  "code": null,
  "errors": null,
  "meta": null
}
```

An error defaults to `400` and needs a 4xx or 5xx status. `errors()` sets its details, such as the messages of each field:

```php title="app/Http/Controllers/PostController.php"
return Response::error('Validation failed.', 422)
    ->errors(['email' => ['The email field is required.']]);
```

`code()` sets a stable identifier the client can act on, such as `auth.expired`. Every error sends `code`, `null` until you set it, and a success never has one:

```php title="app/Http/Controllers/SessionController.php"
return Response::error('Your session has expired.', 401)->code('auth.expired');
```

A wrong status, or `errors()` or `code()` on a success, throws an `InvalidArgumentException` when you call it, so a response never says `success: true` with a 404.

### Metadata

`meta` is always sent after `data` or `errors`, as `null` until something is added. `meta()` adds one key and value, or an array of them; later values replace earlier ones:

```php title="app/Http/Controllers/PostController.php"
return Response::success($post)
    ->meta('version', '1.0')
    ->meta(['editable' => true]);
```

```json title="meta" no-copy
{"version": "1.0", "editable": true}
```

Errors can carry metadata too. `pagination` is reserved for [pagination](#pagination); `meta('pagination', ...)` throws an `InvalidArgumentException`.

### Shared Metadata

`Response::shareMeta()` adds metadata to every response of the current request, so a middleware can set what each controller response should carry:

```php title="app/Http/Middleware/AddApiVersion.php"
namespace App\Http\Middleware;

use Closure;
use Pharaonic\Laravel\ApiResponse\Response;

class AddApiVersion
{
    public function handle($request, Closure $next)
    {
        Response::shareMeta('version', '1.0');

        return $next($request);
    }
}
```

```php title="app/Http/Controllers/PostController.php"
public function show(Post $post)
{
    return Response::success($post); // "meta": {"version": "1.0"}
}
```

It takes a key and a value, or an array, like `meta()`. The metadata is stored on the request, so it never reaches another request, even in a long-running worker. It also reaches the errors of the [exception renderer](#exceptions). A resource's `with()` and `additional()` and the response's own `meta()` replace shared keys, and `pagination` comes last.

### Optional Fields

`notifications`, `seo` and `request_id` appear only when the response has them, in that order after `meta`.

### Building the Response Yourself

`response()` builds the HTTP response for the current request, when you need a concrete response object, for example to add a header:

```php title="app/Http/Controllers/PostController.php"
return Response::success($post)->status(201)->response()
    ->header('Location', url('/api/posts/'.$post->id));
```

`toResponse($request)` does the same for a given request. A response can be built more than once and gives the same result.
