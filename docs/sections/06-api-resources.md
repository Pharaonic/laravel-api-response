## API Resources

A `JsonResource` or a resource collection is resolved with the current request, so conditional attributes (`$this->when()`, `whenLoaded()`) behave as they do in Laravel. Its data is sent under `data` without Laravel's own wrapper, so there is never a `data.data`.

```php title="app/Http/Controllers/PostController.php"
use App\Http\Resources\PostResource;

return Response::success(new PostResource($post));
```

### Collections Over a Paginator

A resource collection over a paginator sends the transformed items and the [pagination metadata](#pagination), once:

```php title="app/Http/Controllers/PostController.php"
return Response::success(PostResource::collection(Post::query()->paginate(15)));
```

### with(), additional() and withResponse()

What a resource returns from `with()` and sets with `additional()` goes to `meta`. Shared metadata comes first. The contents of a `meta` key are merged in and other keys keep their names. Then come the keys of the response's own `meta()`, which replace the resource's, and `pagination` comes last:

```php title="app/Http/Controllers/PostController.php"
return Response::success(PostResource::collection($posts)->additional(['meta' => ['count' => 5], 'version' => 2]));
```

```json title="meta" no-copy
{"version": 2, "count": 5}
```

A resource's `withResponse()` is called with the JSON response, so the headers it sets are sent.

:::info Eager loading is yours
The package only resolves the resource. Load relations in your query (`with('author')`) as you would for any resource.
:::
