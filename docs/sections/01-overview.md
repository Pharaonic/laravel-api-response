:::badges
- Laravel Package {color=blue}
- {release.label} {color=green}
- {package.license} License {color=purple}
:::

# API Response

API Response gives your Laravel API one predictable response shape. You return a `Response` builder from a controller, chain what the response needs in any order, and Laravel turns it into a JSON response when it sends it. Data, API resources and paginators are resolved for you, errors carry stable application codes, and optional parts such as notifications, request IDs and SEO tags appear only when you ask for them.

:::features
### One Envelope {icon="code-brackets"}
success, message, code, data, errors and meta in a fixed order. Fields you never set are left out; values you set, even null or an empty list, are always sent.

### Every Paginator {icon="lines"}
Length-aware, simple and cursor paginators, and your own through an adapter, become their items plus meta.pagination with only the facts the paginator knows.

### API Resources {icon="document"}
JsonResource and resource collections resolve with the current request, keep conditional fields and merge with() and additional() into meta.

### Opt-in Exceptions {icon="alert-circle"}
An exception renderer you register yourself maps API exceptions to the envelope, keeps their headers, and never reports or touches web pages.

### Profiles {icon="switch"}
Rename fields, reshape pagination and notifications, or drop the envelope per response, per route or by Accept media type.

### Request IDs {icon="tag"}
A middleware assigns a validated request ID, sends it in a header and the body, and adds it to every log record of that request.
:::

:::info Nothing is rewritten behind your back
The package shapes only the builders you return. Responses you build with `response()->json()`, views and redirects are untouched, and exception rendering stays off until you register it.
:::
