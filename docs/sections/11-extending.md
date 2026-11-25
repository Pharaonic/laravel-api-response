## Headers, Macros & Transforms

### Headers

`header()` adds one header and `withHeaders()` several. A later value replaces an earlier one, and a response's headers win over its profile's:

```php
return Response::success($posts)
    ->header('X-API-Version', '1')
    ->withHeaders(['Cache-Control' => 'private, max-age=60']);
```

Header names must be valid tokens, and values may not contain line breaks or other control characters, so a header can never be split. Anything else throws an `InvalidArgumentException`.

### Conditional Changes

`when()` and `unless()` change the builder when a condition holds, with an optional default for the other case. A closure as the condition receives the builder, and the callback receives the builder and the condition's value:

```php
return Response::success($post)
    ->when($request->user()->isAdmin(), fn (Response $response) => $response->meta('audit', $post->audits))
    ->unless($post->published, fn (Response $response) => $response->notify('This post is a draft.', 'warning'));
```

Both always return the builder, whatever the callback returns.

### Transforming the Payload

`transform()` changes `data` after resources and paginators are resolved and before the envelope is built. Transforms run in the order they were added and never see the envelope or the metadata:

```php
return Response::success(Post::query()->paginate(15))
    ->transform(fn (array $posts) => array_map(fn ($post) => $post + ['type' => 'post'], $posts));
```

### Macros

Register a macro in a service provider's `boot()` method. Inside the closure, `$this` is the builder:

```php title="app/Providers/AppServiceProvider.php"
use Pharaonic\Laravel\ApiResponse\Response;

public function boot()
{
    Response::macro('version', function (string $version) {
        return $this->header('X-API-Version', $version)->meta('version', $version);
    });
}
```

```php
return Response::success($posts)->version('2');
```

A macro cannot use the name of a method the builder already has; that throws an `InvalidArgumentException`. In tests, `Response::flushMacros()` forgets every macro, and `Response::hasMacro('version')` tells whether one is registered.

### JSON Encoding

`json_options` holds the flags passed to `json_encode()`; Unicode and slashes are sent unescaped by default. `JSON_THROW_ON_ERROR` is always added, so invalid UTF-8, `NAN`, resources and recursive values throw a `JsonException` instead of sending an incomplete or false success. `JSON_PARTIAL_OUTPUT_ON_ERROR` is refused.

```php title="config/pharaonic/api-response.php"
'json_options' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
```
