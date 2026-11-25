## Profiles & Negotiation

A profile decides how a response is shaped: field names, the body, pagination and notification shapes, default headers, and whether the body carries the request ID. The `default` profile follows the configuration. Write your own when one client, such as a mobile app, needs another shape.

### Writing a Profile

Extend `Profiles\Profile` and override only what differs. Profiles are built by the container for each response, so their constructor may type-hint dependencies:

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
        return array_intersect_key($pagination, array_flip(['type', 'pages', 'cursors']));
    }

    public function headers(): array
    {
        return ['X-Profile' => 'mobile'];
    }

    public function includesRequestId(): bool
    {
        return false;
    }
}
```

Register it under a name:

```php title="config/pharaonic/api-response.php"
'profiles' => [
    'default' => Pharaonic\Laravel\ApiResponse\Profiles\Profile::class,
    'mobile' => App\Http\Profiles\MobileProfile::class,
],
```

### What a Profile Controls

| Method | Default | Purpose |
| --- | --- | --- |
| `fields(): array` | `envelope.fields` | The name of each field. Return every field: merge your changes into `parent::fields()`. |
| `body(array $parts): mixed` | the envelope with `fields()` names | Builds the body from the parts that are set, keyed by canonical name. |
| `pagination(array $pagination): array` | unchanged | Shapes `meta.pagination`. |
| `notification(array $notification): array` | drops unset optional fields | Shapes each notification. |
| `headers(): array` | `[]` | Headers for every response of the profile; a response's own headers win. |
| `includesRequestId(): bool` | `request_id.include_in_body` | Whether the body carries the request ID when the response does not say. |
| `paginates(): bool` | `pagination.enabled` | Whether paginators send `meta.pagination`. |

### Sending Data Without an Envelope

Override `body()` to send another shape, such as the data alone:

```php title="app/Http/Profiles/RawProfile.php"
class RawProfile extends Profile
{
    public function body(array $parts): mixed
    {
        return $parts['data'] ?? null;
    }
}
```

### Choosing the Profile

The first of these that applies wins:

1. `->profile('mobile')` on the response;
2. the `UseResponseProfile` middleware on the route;
3. the negotiated `Accept` media type, when content negotiation is on;
4. `default_profile`.

An unknown name in `profile()` or the middleware throws an `InvalidArgumentException`; it never falls back silently.

```php title="routes/api.php"
use Pharaonic\Laravel\ApiResponse\Http\Middleware\UseResponseProfile;

Route::middleware(UseResponseProfile::class.':mobile')->group(function () {
    Route::get('feed', 'FeedController@index');
});
```

To write `profile:mobile` instead, register the class under that key in `$routeMiddleware` of `app/Http/Kernel.php`.

### Content Negotiation

Map media types to profiles and turn negotiation on. The `Accept` header is parsed with its quality values; the best registered media type with a quality above 0 wins, and anything else falls back to the default profile:

```php title="config/pharaonic/api-response.php"
'content_negotiation' => [
    'enabled' => true,
    'media_types' => [
        'application/vnd.acme.mobile+json' => 'mobile',
    ],
],
```

```bash title="Terminal" no-line-numbers
curl -H "Accept: application/vnd.acme.mobile+json" https://example.com/api/feed
```

A negotiated response is sent with the matched media type as its `Content-Type`, and while negotiation is on, every response carries `Vary: Accept`, added to any `Vary` the response, its profile or a resource sets. Profile names are never read from `Accept`: `Accept: mobile` chooses nothing.
