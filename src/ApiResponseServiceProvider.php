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
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/api-response.php' => $this->app->configPath('pharaonic/api-response.php'),
            ], ['pharaonic', 'laravel-api-response', 'pharaonic-config', 'api-response-config']);
        }
    }
}
