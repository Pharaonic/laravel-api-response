## Installation

Install the package with Composer. The service provider is registered automatically, and the package needs no database or migrations.

### Requirements

- PHP 8.3, 8.4 or 8.5
- Laravel 13.x

### Composer Installation

```bash title="Terminal" no-line-numbers
composer require pharaonic/laravel-api-response
```

### Publish Configuration

Publishing is optional; the defaults work as they are. Publish the file to `config/pharaonic/api-response.php` when you want to change them:

```bash title="Terminal" no-line-numbers
php artisan vendor:publish --tag=api-response-config
```

### Publish Translations

The package's messages ship in English and Arabic and follow the application locale. Publish them to `lang/vendor/api-response` to change a line or add a language:

```bash title="Terminal" no-line-numbers
php artisan vendor:publish --tag=api-response-translations
```

### Optional: Exception Rendering and Request IDs

Two pieces are off until you register them in your application: the [exception renderer](#exceptions) and the [`AssignRequestId` middleware](#request-ids), both in `bootstrap/app.php`.

### Optional: SEO Tags

To send SEO tags with `withSEO()`, also install `pharaonic/laravel-seo` 13.1 or later:

```bash title="Terminal" no-line-numbers
composer require pharaonic/laravel-seo
```

:::success Installation Complete
You're all set! Return `Response::success()` from any controller.
:::
