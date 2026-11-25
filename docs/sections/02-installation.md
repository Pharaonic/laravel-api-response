## Installation

Install the package with Composer. The service provider is registered automatically, and the package needs no database or migrations.

### Requirements

- PHP 8.0
- Laravel 6.x (6.20 or later)

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

The package's messages ship in English and Arabic and follow the application locale. Publish them to `resources/lang/vendor/api-response` to change a line or add a language:

```bash title="Terminal" no-line-numbers
php artisan vendor:publish --tag=api-response-translations
```

### Optional: SEO Tags

To send SEO tags with `withSEO()`, also install `pharaonic/laravel-seo` 6.1 or later:

```bash title="Terminal" no-line-numbers
composer require pharaonic/laravel-seo
```

:::success Installation Complete
You're all set! Return `Response::success()` from any controller.
:::
