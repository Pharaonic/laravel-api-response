## SEO Tags

`withSEO()` adds the tags of [pharaonic/laravel-seo](https://pharaonic.dev/packages/laravel/seo) to the response, under `seo`. It is optional: the package does not require laravel-seo, and a response never carries SEO tags without this call, whatever its profile or the configuration say.

```php title="app/Http/Controllers/PageController.php"
seo()->title->set('About us');
seo()->description->set('Who we are.');

return Response::success($page)->withSEO();
```

The tags are what `seo()->toArray()` returns (laravel-seo 6.1 or later), read when the response is built, so set them anywhere before you return.

:::warning laravel-seo must be installed
`withSEO()` without laravel-seo throws a `LogicException` that tells you to run `composer require pharaonic/laravel-seo`, instead of sending a response without the tags you asked for.
:::
