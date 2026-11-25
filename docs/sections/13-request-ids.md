## Request IDs

The `AssignRequestId` middleware gives each request an ID, sends it back in the `X-Request-ID` header, adds it to the response body, and adds it to the log records written during the request.

### Registering the Middleware

Add it to the global middleware, so responses to unknown routes and exceptions get an ID too:

```php title="app/Http/Kernel.php"
protected $middleware = [
    // ...
    \Pharaonic\Laravel\ApiResponse\Http\Middleware\AssignRequestId::class,
];
```

Every response that passes through it gets the header, including responses this package did not build.

### Incoming IDs

An incoming `X-Request-ID` is ignored by default, because clients choose it. Turn on `request_id.trust_incoming` when a proxy you control sets it. A trusted ID is still used only when it is at most `request_id.max_length` characters of letters, digits, `.`, `_`, `:` and `-`; anything else, such as an ID with a line break, is replaced by a new one, so it can never split a header or a log line.

### Generating IDs

IDs are UUIDs by default. To use your own format, set `request_id.generator` to an invokable class; the container builds it, and the ID it returns must pass the same checks, or an `UnexpectedValueException` is thrown:

```php title="app/Support/RequestIdGenerator.php"
namespace App\Support;

use Illuminate\Support\Str;

class RequestIdGenerator
{
    public function __invoke(): string
    {
        return 'req_'.Str::random(24);
    }
}
```

### The ID in the Body

`request_id` is added to the body of every response this package builds while `request_id.include_in_body` is on and the request has an ID. A profile can change that default with `includesRequestId()`, and a response always has the last word:

```php
return Response::success($post)->withoutRequestId();
return Response::success($post)->withRequestId();
```

Without the middleware, the request has no ID and `request_id` is left out, even after `withRequestId()`.

### Log Correlation

While `request_id.logging` is on, a Monolog processor adds the ID to the `extra.request_id` of every record written through the default log channel during the request. The processor reads the ID from the current request each time a record is written, so a long-running worker never writes the ID of an earlier request, and records written outside an HTTP request carry none.

:::info Not distributed tracing
The request ID identifies one request in one application. It is not an OpenTelemetry trace or span ID and is not propagated to outgoing calls.
:::
