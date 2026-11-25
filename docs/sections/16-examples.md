## Use Cases

### 1. A CRUD Controller

One controller with every common response:

```php title="app/Http/Controllers/PostController.php"
namespace App\Http\Controllers;

use App\Http\Requests\StorePost;
use App\Http\Resources\PostResource;
use App\Post;
use Illuminate\Http\Request;
use Pharaonic\Laravel\ApiResponse\Response;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $posts = Post::query()->where('title', 'like', '%'.$request->query('search').'%')->paginate(15);

        return Response::success(PostResource::collection($posts))->withQueryString(['search']);
    }

    public function store(StorePost $request)
    {
        $post = Post::query()->create($request->validated());

        return Response::created(new PostResource($post))
            ->message('Post created.')
            ->notify('Post created.', 'success')
            ->header('Location', url('/api/posts/'.$post->id));
    }

    public function destroy(Post $post)
    {
        $post->delete();

        return Response::noContent();
    }
}
```

### 2. Domain Errors With Stable Codes

Throw a domain exception from your services and map it once:

- ===Exception

  ```php title="app/Exceptions/InsufficientBalance.php"
  namespace App\Exceptions;

  use RuntimeException;

  class InsufficientBalance extends RuntimeException
  {
      public $balance;

      public function __construct(int $balance)
      {
          parent::__construct('Insufficient balance.');

          $this->balance = $balance;
      }
  }
  ```

- ===Mapper

  ```php title="app/Exceptions/InsufficientBalanceMapper.php"
  namespace App\Exceptions;

  use Illuminate\Http\Request;
  use Pharaonic\Laravel\ApiResponse\Response;
  use Pharaonic\Laravel\ApiResponse\Contracts\ExceptionMapper;
  use Throwable;

  class InsufficientBalanceMapper implements ExceptionMapper
  {
      public function map(Throwable $exception, Request $request): ?Response
      {
          return Response::error(__('payments.insufficient_balance'), 422)
              ->code('INSUFFICIENT_BALANCE')
              ->meta('balance', $exception->balance);
      }
  }
  ```

- ===Configuration

  ```php title="config/pharaonic/api-response.php"
  'exceptions' => [
      'paths' => ['api/*'],
      'mappers' => [
          App\Exceptions\InsufficientBalance::class => App\Exceptions\InsufficientBalanceMapper::class,
      ],
  ],
  ```

- ===Handler

  ```php title="app/Exceptions/Handler.php"
  public function render($request, Exception $exception)
  {
      return (new ExceptionRenderer)($exception, $request) ?? parent::render($request, $exception);
  }
  ```

Clients switch on `INSUFFICIENT_BALANCE`, while the message can be translated freely.

### 3. A Mobile Client With Its Own Shape

Serve the same endpoints in a compact shape to the mobile app, chosen by its media type:

- ===Profile

  ```php title="app/Http/Profiles/MobileProfile.php"
  namespace App\Http\Profiles;

  use Pharaonic\Laravel\ApiResponse\Profiles\Profile;

  class MobileProfile extends Profile
  {
      public function fields(): array
      {
          return array_merge(parent::fields(), ['success' => 'ok', 'data' => 'result']);
      }

      public function pagination(array $pagination): array
      {
          return array_intersect_key($pagination, array_flip(['type', 'pages', 'links']));
      }
  }
  ```

- ===Configuration

  ```php title="config/pharaonic/api-response.php"
  'profiles' => [
      'default' => Pharaonic\Laravel\ApiResponse\Profiles\Profile::class,
      'mobile' => App\Http\Profiles\MobileProfile::class,
  ],

  'content_negotiation' => [
      'enabled' => true,
      'media_types' => ['application/vnd.acme.mobile+json' => 'mobile'],
  ],
  ```

The web client keeps the default envelope; requests with `Accept: application/vnd.acme.mobile+json` get `ok` and `result`.

### 4. Tracing a Request Through the Logs

Register the middleware globally, then find every log line of a failing request from the ID the client reports:

```php title="app/Http/Kernel.php"
protected $middleware = [
    // ...
    \Pharaonic\Laravel\ApiResponse\Http\Middleware\AssignRequestId::class,
];
```

```json title="storage/logs/laravel.log" no-copy
[2026-11-24 10:00:00] local.ERROR: Payment gateway timed out {"order":42} {"request_id":"6f1c2a9e-2d4b-4c1f-9a43-3a8d0c7f5b21"}
```

### 5. A Versioned Response Macro

Give every response of a version the same header and metadata in one call:

```php title="app/Providers/AppServiceProvider.php"
use Pharaonic\Laravel\ApiResponse\Response;

public function boot()
{
    Response::macro('apiVersion', function (string $version) {
        return $this->header('X-API-Version', $version)->meta('api_version', $version);
    });
}
```

```php title="app/Http/Controllers/PostController.php"
return Response::success(PostResource::collection(Post::query()->paginate()))->apiVersion('2');
```
