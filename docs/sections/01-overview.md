:::badges
- Laravel Package {color=blue}
- {release.label} {color=green}
- {package.license} License {color=purple}
- AI Ready {color=violet}
:::

# API Response

API Response gives your Laravel API one predictable JSON shape. You return `Response::success()` or `Response::error()` from a controller, chain what the response needs in any order, and Laravel builds the JSON response when it sends it. Resources and paginators are resolved for you, and notifications, request IDs and SEO tags appear only when you use them.

:::features
### One Envelope {icon="code-brackets"}
success, message, data and meta for a success; success, message, errors and meta for an error. Data you set, even null or an empty list, is always sent.

### Every Paginator {icon="lines"}
Length-aware, simple and cursor paginators become their items plus meta.pagination, with only the facts each paginator knows.

### JSON Exceptions {icon="alert-circle"}
A renderer you register yourself maps API exceptions to the error envelope in English or Arabic, keeps their headers and never leaks server errors.

### Profiles {icon="switch"}
A small profile class renames the envelope fields or leaves the request ID out, chosen per response or by default.

### Notifications {icon="check-circle"}
Attach success, info, warning and error messages for the client to show, in the order you add them.

### Request IDs {icon="tag"}
A middleware sends a request ID in a header and the body, and adds it to your log records.

### Shared Metadata {icon="share"}
Add metadata to one response, or share it from a middleware with every response of the request.

### SEO Tags {icon="search"}
Send the page's tags from pharaonic/laravel-seo with one call, never by default.

### AI Ready {icon="bot"}
Laravel Boost guideline and skill included, so php artisan boost:install teaches your coding agent the package.
:::

:::success AI Ready
API Response ships Laravel Boost resources: a guideline loaded in every session and an `api-response-development` skill loaded when your agent works on API responses. Run `php artisan boost:install` and Claude Code, Cursor, Codex, Copilot or Gemini CLI build responses, errors and paginated lists the way the package expects. See [AI Ready](#ai-ready).
:::

:::info Nothing is rewritten behind your back
Only the responses you build with `Response` are shaped. `response()->json()`, views and redirects are untouched, and exception rendering stays off until you register it.
:::

:::warning A redesign in 6.1
6.1 replaces the 6.0 API: the `Response` class, status helpers, error codes, the old profile hooks, adapters, mappers, macros, content negotiation and the package translations are gone. Read the changelog before upgrading from 6.0.
:::
