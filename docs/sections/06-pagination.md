## Pagination

Pass a paginator as the data and the response sends its items in `data` and its facts in `meta.pagination`. Each type sends only what it really knows: a simple paginator never gets a total, and a cursor paginator never gets a page number or item positions.

### The Shape

`meta.pagination` always has the same groups, in this order:

| Key | Length-aware | Simple | Cursor | Holds |
| --- | --- | --- | --- | --- |
| `type` | `length_aware` | `simple` | `cursor` | The kind of paginator. |
| `pages` | yes | yes | yes | `current`, `last`, `per_page` and `has_more`, as far as the paginator knows them. |
| `items` | yes | yes | no | `total`, `from` and `to`: the positions of the page's items. |
| `cursors` | no | no | yes | The `current`, `prev` and `next` cursor tokens. |
| `links` | yes | yes | yes | The page URLs, after the [query-string policy](#query-strings). |

### Length-Aware Paginators

`paginate()`, and a `LengthAwarePaginator` you build yourself:

```php title="app/Http/Controllers/PostController.php"
return Response::success(Post::query()->paginate(15));
```

```json title="Response" no-copy
{
  "success": true,
  "data": [{"id": 1, "title": "First post"}],
  "meta": {
    "pagination": {
      "type": "length_aware",
      "pages": {
        "current": 1,
        "last": 10,
        "per_page": 15,
        "has_more": true
      },
      "items": {
        "total": 150,
        "from": 1,
        "to": 15
      },
      "links": {
        "first": "https://example.com/api/posts?page=1",
        "last": "https://example.com/api/posts?page=10",
        "prev": null,
        "next": "https://example.com/api/posts?page=2"
      }
    }
  }
}
```

On an empty result, `items.from` and `items.to` are `null`, `items.total` is `0` and `pages.last` is `1`.

### Simple Paginators

`simplePaginate()`, and a `Paginator` you build yourself. There is no `pages.last`, no `items.total` and no first or last link:

```json title="meta.pagination" no-copy
{
  "type": "simple",
  "pages": {
    "current": 2,
    "per_page": 15,
    "has_more": true
  },
  "items": {
    "from": 16,
    "to": 30
  },
  "links": {
    "prev": "https://example.com/api/posts?page=1",
    "next": "https://example.com/api/posts?page=3"
  }
}
```

### Cursor Paginators

`cursorPaginate()` exists from Laravel 8.41, so cursor paginators are supported on the 8.x line and later. They have no page numbers and no item positions, so `pages` holds only `per_page` and `has_more`, `items` is left out, and `cursors` holds the encoded tokens; `cursors.current` is `null` on the first page:

```json title="meta.pagination" no-copy
{
  "type": "cursor",
  "pages": {
    "per_page": 15,
    "has_more": true
  },
  "cursors": {
    "current": null,
    "prev": null,
    "next": "eyJpZCI6MTUsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0"
  },
  "links": {
    "prev": null,
    "next": "https://example.com/api/posts?cursor=eyJpZCI6MTUsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0"
  }
}
```

### Custom Page Names

A paginator's own page name is kept in its links, with every query-string policy:

```php
return Response::success(Post::query()->paginate(15, ['*'], 'p'));
// "next": "https://example.com/api/posts?p=2"
```

### Other Paginators

For anything else, such as a page of results from an external API, write a `Contracts\PaginationAdapter` and register it. Send only what your paginator knows, in the same groups (`type`, `pages`, `items` or `cursors`, `links`), so clients read every paginator the same way. Registered adapters are asked before the built-in ones. Pass each URL you send through `$url`, with its page or cursor parameter name, so the response's query-string policy applies to it:

```php title="app/Pagination/RemotePageAdapter.php"
use App\Remote\RemotePage;
use Closure;
use Pharaonic\Laravel\ApiResponse\Contracts\PaginationAdapter;

class RemotePageAdapter implements PaginationAdapter
{
    public function supports(object $paginator): bool
    {
        return $paginator instanceof RemotePage;
    }

    public function items(object $paginator): mixed
    {
        return $paginator->items;
    }

    public function meta(object $paginator, Closure $url): array
    {
        return [
            'type' => 'token',
            'pages' => ['has_more' => $paginator->nextToken !== null],
            'links' => [
                'next' => $paginator->nextToken === null
                    ? null
                    : $url($paginator->path.'?page_token='.$paginator->nextToken, 'page_token'),
            ],
        ];
    }
}
```

```php title="config/pharaonic/api-response.php"
'pagination' => [
    // ...
    'adapters' => [App\Pagination\RemotePageAdapter::class],
],
```

### Turning Pagination Off

`withoutPagination()` sends the items alone, without `meta.pagination`. Set `pagination.enabled` to `false`, or override `paginates()` in a profile, to do it for every response:

```php
return Response::success(Post::query()->paginate(15))->withoutPagination();
```

### Pagination and Metadata

`meta.pagination` belongs to the package: `meta('pagination', ...)` throws an `InvalidArgumentException`. Your other metadata sits next to it.

:::info The paginator is never changed
Links are rewritten when the response is built, from copies. The paginator, its appended query and the request stay as they were.
:::
