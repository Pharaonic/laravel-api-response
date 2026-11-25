## Basic Usage

Start a response with a static constructor, chain what it needs, and return it. `Response` implements Laravel's `Responsable`, so Laravel builds the HTTP response when it sends it.

```php title="app/Http/Controllers/PostController.php"
use App\Post;
use Pharaonic\Laravel\ApiResponse\Response;

class PostController extends Controller
{
    public function show(Post $post)
    {
        return Response::success($post)
            ->message('Post retrieved successfully.')
            ->meta('version', '1.0');
    }
}
```

```json title="Response" no-copy
{
  "success": true,
  "message": "Post retrieved successfully.",
  "data": {"id": 1, "title": "Laravel SEO"},
  "meta": {"version": "1.0"}
}
```

:::info Laravel's Response facade
Laravel aliases its own `Response` facade globally. Importing `Pharaonic\Laravel\ApiResponse\Response` in a file takes precedence there. In a file that also needs Laravel's facade, give one of them another name: `use Pharaonic\Laravel\ApiResponse\Response as ApiResponse;`.
:::

### Errors

An error needs a message and a status of 400 or more. `code()` sets a stable, machine-readable application code, separate from the HTTP status and the message you show:

```php title="app/Http/Controllers/PaymentController.php"
return Response::error('Insufficient balance.', 422)->code('INSUFFICIENT_BALANCE');
```

```json title="Response" no-copy
{
  "success": false,
  "message": "Insufficient balance.",
  "code": "INSUFFICIENT_BALANCE"
}
```

### The Envelope

Fields are always sent in this order, and only when they are set:

| Field | Sent when |
| --- | --- |
| `success` | always: `true` below 400, `false` from 400 |
| `message` | `message()` was called, or the response is an error |
| `code` | `code()` was called, or a helper set one |
| `data` | the constructor received data, or `data()` was called |
| `errors` | `errors()` was called |
| `meta` | there is metadata from `meta()`, a resource, or pagination |
| `notifications` | `notify()` was called |
| `seo` | `withSEO()` was called |
| `request_id` | the request has an ID and the response includes it |

### Unset Is Not Empty

A field you never set is left out. A value you set is always sent, even `null`, `[]`, `false`, `0` or `''`:

```php
Response::success();         // {"success":true}
Response::success(null);     // {"success":true,"data":null}
Response::success([]);       // {"success":true,"data":[]}
```

### Data Types

`data` accepts arrays, scalars, Eloquent models and collections, `Arrayable`, `JsonSerializable` and `Jsonable` objects, API resources and paginators. Models keep their `$hidden` and `$casts` rules, and resources are never wrapped twice, so there is no `data.data`.

### Order Does Not Matter

Every fluent method only records state, and the response is built at the end, so these two give the same response:

```php
Response::success($post)->message('Saved.')->notify('Saved.', 'success');
Response::success()->notify('Saved.', 'success')->data($post)->message('Saved.');
```

Setters such as `data()`, `message()`, `code()`, `status()` and `profile()` keep the last value. `meta()`, `notify()`, `header()` and `transform()` add to what is already there. Building the same builder twice gives the same response, without duplicating notifications, SEO tags or metadata.

### Building the Response Yourself

Call `response()` to get the HTTP response for the current request, for example inside middleware or a test:

```php
$response = Response::success(['ok' => true])->response();

$response->getStatusCode(); // 200
```
