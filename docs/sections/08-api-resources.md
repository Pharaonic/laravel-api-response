## API Resources

Laravel's `JsonResource` and resource collections are resolved with the current request through their own `resolve()`, so `when()`, `whenLoaded()`, `mergeWhen()`, nested resources and `$collects` keep working. Their output goes into `data` without Laravel's `data` wrapper.

### A Single Resource

```php title="app/Http/Controllers/PostController.php"
use App\Http\Resources\PostResource;

return Response::success(new PostResource($post));
```

### Collections and Paginated Collections

A collection of an unpaginated query sends a list. A collection of a paginator sends the transformed items and the normalized `meta.pagination`, for anonymous and custom collections alike:

```php
return Response::success(PostResource::collection(Post::query()->paginate(15)));
return Response::success(new PostCollection(Post::query()->simplePaginate(15)));
```

An empty collection is sent as `"data": []`.

### Resource Metadata

A resource's `with()` and `additional()` become metadata. The contents of their `meta` key are merged into `meta`; every other key, such as `links`, keeps its own name inside `meta`:

```php title="app/Http/Resources/PostResource.php"
public function with($request)
{
    return [
        'meta' => ['resource' => 'post'],
        'links' => ['self' => url('/api/posts/'.$this->id)],
    ];
}
```

```json title="Response" no-copy
{
  "success": true,
  "data": {"id": 1, "title": "Laravel SEO"},
  "meta": {
    "links": {"self": "https://example.com/api/posts/1"},
    "resource": "post"
  }
}
```

### Collision Rules

Metadata merges in a fixed order, and a later key replaces an earlier one:

1. the resource's `with()`, then its `additional()`;
2. the response's `meta()`;
3. `meta.pagination`, which only the package sets.

### Custom Pagination Information

When a resource collection defines `paginationInformation($request, $paginated, $default)`, it is called with Laravel's paginator array as `$paginated` and `['pagination' => ...]`, the normalized pagination, as `$default`. The `pagination` key it returns becomes `meta.pagination` (leave it out to send none), and its other keys are merged into `meta`:

```php title="app/Http/Resources/PostCollection.php"
public function paginationInformation($request, $paginated, $default)
{
    $default['pagination']['label'] = 'posts';

    return $default;
}
```

### Status and withResponse()

The resource's `withResponse()` runs on the final response, so headers it sets are kept. The status always comes from the builder: Laravel's automatic `201` for a model created in this request is not applied, so return `Response::created(...)` instead.
