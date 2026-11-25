## Query Strings

Pagination links can keep the request's query string, so a client following `next` keeps its search and sort. The page or cursor parameter is always kept, and denied keys never are.

### The Three Policies

```php
// Every key except the denied ones (the default).
->withQueryString()

// Only these keys, still without the denied ones.
->withQueryString(['search', 'sort'])

// No extra keys; the page or cursor parameter stays.
->withoutQueryString()
```

For a request to `/api/posts?search=café&sort=-id&token=secret&page=2`:

| Policy | `next` link |
| --- | --- |
| default | `/api/posts?search=caf%C3%A9&sort=-id&page=3` |
| `withQueryString(['search'])` | `/api/posts?search=caf%C3%A9&page=3` |
| `withoutQueryString()` | `/api/posts?page=3` |

`token` is left out in every case, because it is in `pagination.query_string.except`.

### The Default

`pagination.query_string.enabled` chooses what a response does when it calls neither method. Set it to `false` to keep only the page parameter unless a response calls `withQueryString()`.

### Denied Keys

`pagination.query_string.except` lists keys that are never copied into a link, compared without case, even when an allowlist names them. Add every key that can carry a credential:

```php title="config/pharaonic/api-response.php"
'query_string' => [
    'enabled' => true,
    'except' => ['token', 'api_key', 'access_token', 'password', 'signature'],
],
```

:::warning Links echo what the client sent
A preserved query string repeats the client's own values in the response. Keep credentials and signed values in `except`, or use an allowlist on public endpoints.
:::

### What Is Rewritten

Only the pagination links the package builds are rewritten. Keys the paginator appended itself (`appends()`) follow the same policy and win over request keys with the same name. Nested keys (`filter[status]`), non-ASCII values and cursor tokens are encoded with RFC 3986. URLs inside your own data are never touched.
