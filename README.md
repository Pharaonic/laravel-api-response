<p align="center"><a href="https://pharaonic.dev/packages/laravel/api-response" target="_blank"><img src="https://raw.githubusercontent.com/Pharaonic/laravel-api-response/develop/docs/cover.webp"></a></p>

<p align="center">
  <a href="https://php.net" target="_blank"><img src="https://img.shields.io/static/v1?label=PHP&message=8.2%20|%208.3%20|%208.4&color=blue&style=flat-square" alt="PHP Version : 8.2 | 8.3 | 8.4"></a>
  <a href="https://laravel.com" target="_blank"><img src="https://img.shields.io/static/v1?label=Laravel&message=11.x&color=F05340&style=flat-square" alt="Laravel Version : 11.x"></a>
  <img src="https://img.shields.io/static/v1?label=License&message=MIT&color=brightgreen&style=flat-square" alt="License">
  <img src="https://github.com/Pharaonic/laravel-api-response/actions/workflows/build.yml/badge.svg?branch=11.x" alt="Build">
  <br>
  <a href="https://packagist.org/packages/Pharaonic/laravel-api-response" target="_blank"><img src="https://img.shields.io/static/v1?label=Packagist&message=pharaonic/laravel-api-response&color=blue&logo=packagist&logoColor=white" alt="Source"></a>
  <a href="https://packagist.org/packages/pharaonic/laravel-api-response" target="_blank"><img src="https://poser.pugx.org/pharaonic/laravel-api-response/v" alt="Packagist Version"></a>
  <a href="https://packagist.org/packages/pharaonic/laravel-api-response" target="_blank"><img src="https://poser.pugx.org/pharaonic/laravel-api-response/downloads" alt="Packagist Downloads"></a>
  <a href="https://pharaonic.dev/packages/laravel/api-response#ai-ready" target="_blank"><img src="https://img.shields.io/static/v1?label=AI%20Ready&message=Laravel%20Boost&color=8B5CF6&style=flat-square" alt="AI Ready: Laravel Boost"></a>
</p>

<h3 align="center">One consistent envelope for Laravel API responses: success and error responses, resources and every paginator, with profiles, notifications, request IDs and opt-in exception rendering.</h3>
<br>

## Installation

```bash
composer require pharaonic/laravel-api-response
```

The service provider is registered automatically. Publish the configuration when you need to change it:

```bash
php artisan vendor:publish --tag=api-response-config
```

Messages follow the application locale (English and Arabic included). Publish them to change a line or add a language:

```bash
php artisan vendor:publish --tag=api-response-translations
```

## Usage

```php
use App\Http\Resources\PostResource;
use App\Models\Post;
use Pharaonic\Laravel\ApiResponse\Response;

class PostController
{
    public function index()
    {
        return Response::success(PostResource::collection(Post::query()->paginate(15)))
            ->message('Posts retrieved.')
            ->notify('Posts loaded.', 'success');
    }

    public function show($id)
    {
        return Response::error('Post not found.', 404);
    }
}
```

## Documentation

Full documentation: [Laravel API Response Documentation](https://pharaonic.dev/packages/laravel/api-response).

## Support & Security

See [SUPPORT.md](SUPPORT.md) and [SECURITY.md](SECURITY.md). Contributions are welcome; see [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT. See [LICENSE](LICENSE).
