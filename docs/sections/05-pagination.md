## Pagination

Pass a paginator, or a resource collection over one, and its items become `data` while its metadata goes to `meta.pagination`. Each type sends only what its paginator knows.

### Length-Aware

```php title="app/Http/Controllers/PostController.php"
return Response::success(Post::query()->paginate(15));
```

```json title="Response" no-copy
{
  "success": true,
  "message": null,
  "data": [{"id": 16, "title": "..."}],
  "meta": {
    "pagination": {
      "type": "length_aware",
      "current_page": 2,
      "last_page": 4,
      "per_page": 15,
      "total": 52,
      "from": 16,
      "to": 30,
      "has_more_pages": true,
      "links": {
        "first": "https://example.com/api/posts?page=1",
        "last": "https://example.com/api/posts?page=4",
        "prev": "https://example.com/api/posts?page=1",
        "next": "https://example.com/api/posts?page=3"
      }
    }
  }
}
```

### Simple

`simplePaginate()` does not count rows, so there is no `total` or `last_page`:

```json title="meta.pagination" no-copy
{
  "type": "simple",
  "current_page": 1,
  "per_page": 15,
  "from": 1,
  "to": 15,
  "has_more_pages": true,
  "links": {"prev": null, "next": "https://example.com/api/posts?page=2"}
}
```

### Cursor

`cursorPaginate()` has no page numbers or totals:

```json title="meta.pagination" no-copy
{
  "type": "cursor",
  "per_page": 15,
  "next_cursor": "eyJpZCI6MTUsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0",
  "prev_cursor": null,
  "has_more_pages": true,
  "links": {"prev": null, "next": "https://example.com/api/posts?cursor=eyJpZCI6MTUs..."}
}
```

An empty page sends `data: []`, with `from` and `to` set to `null`.

### Query Strings

By default every pagination link keeps the request's query string, so `?search=php&page=2` links to `?search=php&page=3`. The page or cursor parameter is always the paginator's own.

| Call | Links keep |
| --- | --- |
| nothing | what `pagination.query_string` says (`true`: every key) |
| `withQueryString()` | every key |
| `withQueryString(['search', 'sort'])` | only `search` and `sort` |
| `withoutQueryString()` | only the page or cursor parameter |

```php title="app/Http/Controllers/PostController.php"
return Response::success(Post::query()->paginate(15))
    ->withQueryString(['search', 'sort']);
```

The keys in `pagination.sensitive_query` (`token`, `api_key`, `access_token`, `password`) are never copied into a link, even when you name them. The request and the paginator are never changed; only the links in the body are rebuilt.

:::tip A custom page name
`paginate(15, ['*'], 'p')` keeps `p` as the page parameter in every link.
:::
