## Profiles

A profile is a class that extends `Pharaonic\Laravel\ApiResponse\Profile`. It can rename envelope fields and decide whether the body carries the request ID. Override only what differs from the defaults:

```php title="app/Http/Profiles/MobileProfile.php"
namespace App\Http\Profiles;

use Pharaonic\Laravel\ApiResponse\Profile;

class MobileProfile extends Profile
{
    public function fields(): array
    {
        return ['data' => 'result', 'message' => 'msg'];
    }

    public function requestIdInBody(): bool
    {
        return false;
    }
}
```

Register it under a name:

```php title="config/pharaonic/api-response.php"
'profiles' => [
    'default' => Pharaonic\Laravel\ApiResponse\Profile::class,
    'mobile' => App\Http\Profiles\MobileProfile::class,
],
```

Choose it on a response:

```php title="app/Http/Controllers/PostController.php"
return Response::success($post)->profile('mobile');
```

```json title="Response" no-copy
{
  "success": true,
  "msg": null,
  "result": {"id": 1},
  "meta": null
}
```

Without `profile()`, a response uses `default_profile`, and so do the errors of the [exception renderer](#exceptions).

### Methods

| Method | Default | Description |
| --- | --- | --- |
| `fields(): array` | `[]` | The fields this profile renames, canonical name => sent name. Fields it leaves out keep their own name. |
| `requestIdInBody(): bool` | `true` | Whether the body carries `request_id` when the request has one. |

Profiles are built by the container for each response, so a constructor may type-hint dependencies such as the config repository.

### Rules

- Fields you can rename: `success`, `message`, `data`, `errors`, `meta`, `notifications`, `seo`, `request_id`.
- An unknown profile name, a class that does not extend `Profile`, an unknown field, an empty name, or two fields under the same name throw an `InvalidArgumentException` when the response is built.
- A profile never adds SEO tags; only [`withSEO()`](#seo) does.
- `withRequestId()` adds the request ID to one response even when its profile leaves it out.
