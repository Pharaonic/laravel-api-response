---
view: components.packages.features
variant: compact
badge: Key Features
title: Everything an API response needs
subtitle: Return a builder from your controller; Laravel turns it into the response.
items:
  - icon: code-brackets
    title: One Envelope
    text: "`success`, `message`, `code`, `data`, `errors` and `meta`, with unset fields left out and your own `null`, `[]` or `0` always kept."
  - icon: lines
    title: Every Paginator
    text: Length-aware, simple and cursor paginators become their items plus `meta.pagination`, without invented totals.
  - icon: document
    title: API Resources
    text: "`JsonResource` and resource collections resolve with the current request, keeping conditional fields, `with()` and `additional()`."
  - icon: alert-circle
    title: JSON Exceptions
    text: An opt-in renderer maps validation, auth, 404, throttling and your own exceptions, keeps headers, and hides 5xx details.
  - icon: switch
    title: Profiles
    text: Rename fields, reshape pagination or drop the envelope per route, per response, or by `Accept` media type.
  - icon: tag
    title: Request IDs
    text: A middleware sends a safe request ID in a header and the body, and adds it to your log records.
---
