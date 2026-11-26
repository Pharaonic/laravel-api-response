## Configuration

The configuration is merged under `pharaonic.api-response` and read every time a response is built, so a change in a test or a service provider applies right away.

```php title="config/pharaonic/api-response.php"
return [
    'default_profile' => 'default',

    'profiles' => [
        'default' => Pharaonic\Laravel\ApiResponse\Profile::class,
    ],

    'pagination' => [
        'query_string' => true,
        'sensitive_query' => ['token', 'api_key', 'access_token', 'password'],
    ],

    'exceptions' => [
        'paths' => ['api/*'],
    ],

    'request_id' => [
        'header' => 'X-Request-ID',
        'trust_incoming' => false,
        'logging' => true,
    ],
];
```

| Key | Default | Description |
| --- | --- | --- |
| `default_profile` | `default` | The profile a response uses unless it calls `profile()`. |
| `profiles` | `default` => `Profile::class` | Profile names and their [classes](#profiles). |
| `pagination.query_string` | `true` | Keep the request's query string in pagination links, unless a response calls `withQueryString()` or `withoutQueryString()`. |
| `pagination.sensitive_query` | `token`, `api_key`, `access_token`, `password` | Query keys never copied into a pagination link, compared without case. |
| `exceptions.paths` | `api/*` | Paths whose exceptions the registered renderer sends as JSON, besides requests that expect JSON. |
| `request_id.header` | `X-Request-ID` | The header `AssignRequestId` sends the ID in. |
| `request_id.trust_incoming` | `false` | Reuse a valid incoming ID instead of generating one. |
| `request_id.logging` | `true` | Add the ID to `extra.request_id` of the default log channel's records. |
