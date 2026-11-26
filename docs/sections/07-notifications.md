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

### Title, code, action, duration and position

Every other part is optional and sent only when you give it, so pass the ones you need by name:

```php title="app/Http/Controllers/PostController.php"
return Response::success($post)->notify(
    'Your post is live.',
    'success',
    title: 'Published',
    code: 'post.published',
    action: ['label' => 'View post', 'url' => route('posts.show', $post)],
    duration: 5000,
    position: 'top-right',
);
```

```json title="notifications" no-copy
[
  {
    "type": "success",
    "title": "Published",
    "message": "Your post is live.",
    "code": "post.published",
    "action": {"label": "View post", "url": "https://example.com/posts/1"},
    "duration": 5000,
    "position": "top-right"
  }
]
```

| Part | Value |
| --- | --- |
| `title` | A heading shown above the message. |
| `code` | A stable identifier the client can translate or act on, such as `post.published`. |
| `action` | A button: exactly a non-empty `label` and `url`. |
| `duration` | How long to show it, in milliseconds. `0` keeps it until it is dismissed; a negative value throws. |
| `position` | `top-left`, `top-center`, `top-right`, `bottom-left`, `bottom-center` or `bottom-right`. |

The keys always come in the order `type`, `title`, `message`, `code`, `action`, `duration`, `position`. A wrong action, duration or position throws an `InvalidArgumentException`. Leave `duration` and `position` out to let the client decide.

:::info Response metadata only
Notifications are part of the response body. They have nothing to do with Laravel's mail, database or broadcast notifications, and nothing is stored or sent elsewhere.
:::
