## Use Cases

### 1. A CRUD Controller

One controller with the common responses:

```php title="app/Http/Controllers/PostController.php"
namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;
use Pharaonic\Laravel\ApiResponse\Response;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $posts = Post::query()->where('title', 'like', '%'.$request->query('search').'%')->paginate(15);

        return Response::success(PostResource::collection($posts))->withQueryString(['search']);
    }

    public function store(Request $request)
    {
        $post = Post::query()->create($request->validate(['title' => 'required|string']));

        return Response::success(new PostResource($post))
            ->status(201)
            ->message('Post created.')
            ->notify('Post created.', 'success');
    }

    public function destroy(Post $post)
    {
        $post->delete();

        return Response::success()->status(204);
    }
}
```

A failed `validate()` becomes a 422 with the field errors once the [exception renderer](#exceptions) is registered.

### 2. A Domain Error

Return the error from where you know it happened:

```php title="app/Http/Controllers/OrderController.php"
if ($user->balance < $total) {
    return Response::error('Insufficient balance.', 422)
        ->errors(['balance' => ['Your balance is too low.']])
        ->meta(['balance' => $user->balance, 'required' => $total])
        ->notify('Top up your wallet to continue.', 'warning');
}
```

### 3. A Mobile API With Its Own Field Names

- ===Profile

  ```php title="app/Http/Profiles/MobileProfile.php"
  namespace App\Http\Profiles;

  use Pharaonic\Laravel\ApiResponse\Profile;

  class MobileProfile extends Profile
  {
      public function fields(): array
      {
          return ['data' => 'result'];
      }

      public function requestIdInBody(): bool
      {
          return false;
      }
  }
  ```

- ===Configuration

  ```php title="config/pharaonic/api-response.php"
  'profiles' => [
      'default' => Pharaonic\Laravel\ApiResponse\Profile::class,
      'mobile' => App\Http\Profiles\MobileProfile::class,
  ],
  ```

- ===Controller

  ```php title="app/Http/Controllers/Mobile/FeedController.php"
  return Response::success(Post::query()->latest()->simplePaginate(20))
      ->profile('mobile')
      ->withoutQueryString();
  ```

### 4. Tracing a Request Through the Logs

With `AssignRequestId` in the `api` group, a client that reports an error sends you the `X-Request-ID` it received, and you find every log line of that request by `extra.request_id`.
