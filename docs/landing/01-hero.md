---
view: components.packages.package-hero
badges:
  - label: Laravel Package
    color: blue
  - label: "{package.latestVersionLabel}"
    color: green
  - label: "{package.license} License"
    color: purple
  - label: "{package.downloadsShort}+ downloads"
    color: blue
  - label: AI Ready · Laravel Boost
    color: violet
eyebrow: "{package.name}"
title: One envelope
highlight: for every API response
buttons:
  - label: View Full Documentation
    href: "{card.docsUrl}"
    style: primary
    icon: arrow-right
  - label: View on GitHub
    href: "{package.githubUrl}"
    style: ghost
    external: true
install: "{card.install}"
labels:
  copy: Copy
  copied: Copied!
---

{package.name} gives every API response the same shape. Return `Response::success($posts)` or `Response::error('Post not found.', 404)` from a controller and get your data, API resources and paginators in one predictable envelope, with notifications, request IDs and opt-in JSON exceptions. Responses you build without it are left alone. It is AI ready: `php artisan boost:install` teaches your coding agent the whole package through Laravel Boost.
