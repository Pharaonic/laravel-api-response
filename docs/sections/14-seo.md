## SEO Tags

With `pharaonic/laravel-seo` 6.1 or later installed, `withSEO()` adds the current page's SEO tags to the response, for a front end that renders them itself.

```php title="app/Http/Controllers/PostController.php"
seo()->title->set($post->title);
seo()->description->set($post->excerpt);

return Response::success(new PostResource($post))->withSEO();
```

```json title="Response" no-copy
{
  "success": true,
  "data": {"id": 1, "title": "Laravel SEO"},
  "seo": {"title": "Laravel SEO", "description": "Meta tags from code."}
}
```

### Only When Asked

A response never carries `seo` unless that response calls `withSEO()`, whatever the configuration, the profile, or whether the package is installed. The tags are read once, from `seo()->toArray()`, when the response is built, so tags set after `withSEO()` in the same controller are included. The field follows the envelope's `seo` name.

### Without laravel-seo

`withSEO()` without the package (or with a release older than 6.1) throws a `LogicException` that says how to install it. Set `seo.missing_driver` to `ignore` to leave the field out instead:

```php title="config/pharaonic/api-response.php"
'seo' => [
    'missing_driver' => 'ignore',
],
```
