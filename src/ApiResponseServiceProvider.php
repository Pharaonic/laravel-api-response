<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse;

use Illuminate\Support\ServiceProvider;

final class ApiResponseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/api-response.php', 'pharaonic.api-response');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'api-response');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/api-response.php' => $this->app->configPath('pharaonic/api-response.php'),
            ], ['pharaonic', 'laravel-api-response', 'pharaonic-config', 'api-response-config']);

            $this->publishes([
                __DIR__.'/../resources/lang' => lang_path('vendor/api-response'),
            ], ['pharaonic', 'laravel-api-response', 'pharaonic-translations', 'api-response-translations']);
        }
    }
}
