## Notifications

`notify()` adds a message for the client to display, such as a toast. Notifications are part of the response body only; nothing is sent through Laravel's notification system.

```php title="app/Http/Controllers/PostController.php"
return Response::created(new PostResource($post))
    ->notify('Post created.', 'success')
    ->notify('Waiting for review.');
```

```json title="Response" no-copy
{
  "success": true,
  "data": {"id": 1, "title": "Laravel SEO"},
  "notifications": [
    {"type": "success", "message": "Post created."},
    {"type": "info", "message": "Waiting for review."}
  ]
}
```

### Structured Fields

Each notification has a `type` and a `message`, and optionally a `title`, a `duration` in milliseconds and `data`. Named arguments read best:

```php
return Response::success(new PostResource($post))->notify(
    message: 'Post created successfully.',
    type: 'success',
    title: 'Done',
    duration: 5000,
    data: ['post_id' => $post->id],
);
```

Optional fields that are not set are left out. Notifications keep the order they were added in, and an error response can carry them too.

### Types

A notification without a type uses `notifications.default_type` (`info`). The type must be one of `notifications.types`, so add your own there:

```php title="config/pharaonic/api-response.php"
'notifications' => [
    'default_type' => 'info',
    'types' => ['success', 'error', 'warning', 'info', 'celebrate'],
],
```

An unknown type throws an `InvalidArgumentException` when the response is built, and a negative duration throws when `notify()` is called.

### Custom Shape

Override `notification()` in a [profile](#profiles) to send notifications in another shape:

```php title="app/Http/Profiles/MobileProfile.php"
public function notification(array $notification): array
{
    return ['text' => $notification['message'], 'kind' => $notification['type']];
}
```

:::info Bodyless responses
A `204` or `304` response, or the response to a `HEAD` request, has no body, so its notifications are not sent.
:::
