## AI Ready

API Response is AI ready. It ships [Laravel Boost](https://laravel.com/docs/boost) resources, so the coding agent in your application (Claude Code, Cursor, Codex, Copilot, Gemini CLI, Junie…) knows how to use the package.

### Install

With Laravel Boost in your application, run:

```bash title="Terminal" no-line-numbers
php artisan boost:install
```

Boost finds the package's guideline and skill in `vendor/` and adds them for the agents you choose. After updating the package, refresh them with:

```bash title="Terminal" no-line-numbers
php artisan boost:update
```

### What the Agent Learns

| Resource | Loaded | Covers |
| --- | --- | --- |
| Guideline (`resources/boost/guidelines/core.blade.php`) | In every session | The envelope, `Response::success()` and `error()`, paginators and query strings, and registering the exception renderer |
| Skill `api-response-development` | When the agent works on API responses | Every public method, the configuration, pagination shapes, profiles, shared metadata, the exception renderer, request IDs, SEO tags, pitfalls and testing |

:::tip Keep Them Current
Add `@php artisan boost:update --ansi` to your Composer `post-update-cmd` scripts so the agent always matches the installed release.
:::
