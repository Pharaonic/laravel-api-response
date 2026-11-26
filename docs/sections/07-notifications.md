## Notifications

`notify()` attaches a message for the client to show, such as a toast. Call it as often as you need; notifications keep the order you add them in.

```php title="app/Http/Controllers/PostController.php"
return Response::success($post)
    ->notify('Post created.', 'success')
    ->notify('Your post is waiting for review.');
```

```json title="notifications" no-copy
[
  {"type": "success", "message": "Post created."},
  {"type": "info", "message": "Your post is waiting for review."}
]
```

The type is `success`, `info` (the default), `warning` or `error`; any other type throws an `InvalidArgumentException`. Errors can notify too.

:::info Response metadata only
Notifications are part of the response body. They have nothing to do with Laravel's mail, database or broadcast notifications, and nothing is stored or sent elsewhere.
:::
