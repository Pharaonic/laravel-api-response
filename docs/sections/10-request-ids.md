## Request IDs

The `AssignRequestId` middleware gives each request an ID, sends it back in the `X-Request-ID` header, adds it to the response body and to your log records.

### Registering the Middleware

Add it to the `api` group, or globally to give every response the header:

```php title="app/Http/Kernel.php"
protected $middlewareGroups = [
    'api' => [
        \Pharaonic\Laravel\ApiResponse\Http\Middleware\AssignRequestId::class,
        'throttle:60,1',
        'bindings',
    ],
];
```

Put it first in the group, so responses its later middleware return, such as a 429 from throttling, get the header too.

### Header and Body

Every response that passes through the middleware gets the header, including errors the [exception renderer](#exceptions) sends. A `Response` also carries it in the body, unless its [profile](#profiles) returns `false` from `requestIdInBody()`:

```json title="Response" no-copy
{
  "success": true,
  "message": null,
  "data": {"id": 1},
  "meta": null,
  "request_id": "0f6b1a8e-3b8c-4f61-9d6a-0f0d9a3f3e21"
}
```

A response built outside the middleware has no request ID and no `request_id` field.

### Incoming IDs

An incoming `X-Request-ID` is untrusted and ignored by default: every request gets a new UUID. Set `request_id.trust_incoming` to `true` to reuse it, for example behind a gateway that assigns IDs. Even then, it is reused only when it is 1 to 128 letters, digits, `.`, `_`, `:` or `-`; anything else is replaced by a UUID.

### Log Records

With `request_id.logging` on, every record of the default log channel gets `extra.request_id`. The ID is read from the current request when the record is written and lives only on that request, so a long-running worker never logs the ID of an earlier request.

```php title="app/Http/Controllers/OrderController.php"
Log::info('Order placed.'); // extra: {"request_id": "0f6b1a8e-..."}
```
