## Configuration

Every option lives in `config/pharaonic/api-response.php` and is read under `pharaonic.api-response`. Options are checked when a response is built, and a wrong value throws an `InvalidArgumentException` that names the key.

### Options

| Key | Default | Description |
| --- | --- | --- |
| `default_profile` | `'default'` | The profile a response uses when nothing else chooses one. |
| `profiles` | `['default' => Profile::class]` | Profile names and their classes, each extending `Pharaonic\Laravel\ApiResponse\Profiles\Profile`. |
| `json_options` | `JSON_UNESCAPED_UNICODE \| JSON_UNESCAPED_SLASHES` | Flags for `json_encode()`. `JSON_THROW_ON_ERROR` is always added and `JSON_PARTIAL_OUTPUT_ON_ERROR` is refused. |
| `envelope.fields` | each field under its own name | The name each envelope field is sent under: `success`, `message`, `code`, `data`, `errors`, `meta`, `notifications`, `seo`, `request_id`. Names must be unique. |
| `pagination.enabled` | `true` | Send paginators as their items plus `meta.pagination`. |
| `pagination.query_string.enabled` | `true` | Keep the request's query string in pagination links by default. |
| `pagination.query_string.except` | `['token', 'api_key', 'access_token', 'password']` | Keys never copied into pagination links, compared without case. |
| `pagination.adapters` | `[]` | Your own `Contracts\PaginationAdapter` classes, asked before the built-in ones. |
| `notifications.default_type` | `'info'` | The type of a notification that names none. Must be one of `types`. |
| `notifications.types` | `['success', 'error', 'warning', 'info']` | The notification types a response may use. |
| `seo.missing_driver` | `'throw'` | What `withSEO()` does without `pharaonic/laravel-seo`: `'throw'` or `'ignore'`. |
| `request_id.header` | `'X-Request-ID'` | The header that carries the request ID, in and out. |
| `request_id.generator` | `'uuid'` | `'uuid'`, or an invokable class that returns the ID. |
| `request_id.trust_incoming` | `false` | Reuse a valid incoming ID instead of generating one. |
| `request_id.max_length` | `128` | The longest incoming or generated ID accepted (1 to 1024). |
| `request_id.include_in_body` | `true` | Add the ID to the body unless the response or profile says otherwise. |
| `request_id.logging` | `true` | Add the ID to log records as `extra.request_id`. |
| `exceptions.paths` | `['api/*']` | Request paths rendered as JSON even when the request does not ask for JSON. |
| `exceptions.mappers` | `[]` | Exception class or interface => `Contracts\ExceptionMapper` class. |
| `content_negotiation.enabled` | `false` | Choose the profile from the `Accept` header. |
| `content_negotiation.media_types` | `[]` | Media type => profile name, e.g. `'application/vnd.acme.mobile+json' => 'mobile'`. |

### Renaming Envelope Fields

Rename a field for every response by changing its name:

```php title="config/pharaonic/api-response.php"
'envelope' => [
    'fields' => [
        'success' => 'ok',
        'message' => 'message',
        'code' => 'code',
        'data' => 'result',
        'errors' => 'errors',
        'meta' => 'meta',
        'notifications' => 'notifications',
        'seo' => 'seo',
        'request_id' => 'request_id',
    ],
],
```

:::warning Every field needs a unique name
Two fields under the same name, a missing field, or an unknown one throws an `InvalidArgumentException` when a response is built, instead of silently overwriting data.
:::
