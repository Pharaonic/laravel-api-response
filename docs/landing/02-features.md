---
view: components.packages.features
variant: compact
badge: Key Features
title: Everything an API response needs
subtitle: Return the response from your controller; Laravel builds it.
items:
  - icon: code-brackets
    title: One Envelope
    text: "`success`, `message`, `data` or `errors`, and `meta`, with your own `null`, `[]` or `0` always kept and optional fields only when used."
  - icon: lines
    title: Every Paginator
    text: Length-aware, simple and cursor paginators become their items plus `meta.pagination`, without invented totals, with query-string control.
  - icon: document
    title: API Resources
    text: "`JsonResource` and resource collections resolve with the current request, never as `data.data`, keeping `with()` and `additional()`."
  - icon: alert-circle
    title: JSON Exceptions
    text: An opt-in renderer maps validation, auth, 404, throttling and server errors with translated messages, keeps their headers and never leaks 500 details.
  - icon: switch
    title: Profiles
    text: A small `Profile` class renames envelope fields or leaves the request ID out, chosen with `profile('mobile')` or as the default.
  - icon: tag
    title: Request IDs
    text: A middleware sends a request ID in a header and the body, and adds it to your log records.
  - icon: check-circle
    title: Notifications
    text: "`notify()` attaches success, info, warning and error messages for the client to show, in the order you add them, with an optional title, code, action button, duration and position."
  - icon: share
    title: Shared Metadata
    text: "`meta()` on one response, or `Response::shareMeta()` in a middleware for every response of the request."
  - icon: bot
    title: AI Ready
    text: "Ships a Laravel Boost guideline and an `api-response-development` skill, so `php artisan boost:install` teaches Claude Code, Cursor, Codex or Copilot the whole API."
---
