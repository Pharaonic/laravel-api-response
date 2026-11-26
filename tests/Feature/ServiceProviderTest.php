<?php

use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\ApiResponse\ApiResponseServiceProvider;

test('the config is merged under pharaonic', function () {
    $this->assertSame('default', $this->app['config']->get('pharaonic.api-response.default_profile'));
    $this->assertSame('X-Request-ID', $this->app['config']->get('pharaonic.api-response.request_id.header'));
    $this->assertSame(['api/*'], $this->app['config']->get('pharaonic.api-response.exceptions.paths'));
});

test('the config is published under its tags', function () {
    foreach (['pharaonic-config', 'api-response-config'] as $tag) {
        $paths = ServiceProvider::pathsToPublish(ApiResponseServiceProvider::class, $tag);

        $this->assertSame([$this->app->configPath('pharaonic/api-response.php')], array_values($paths), $tag);
    }
});

test('the translations are published under their tags', function () {
    foreach (['pharaonic-translations', 'api-response-translations'] as $tag) {
        $paths = ServiceProvider::pathsToPublish(ApiResponseServiceProvider::class, $tag);

        $this->assertSame([$this->app->resourcePath('lang/vendor/api-response')], array_values($paths), $tag);
    }
});

test('the shared tags publish the config and the translations', function () {
    foreach (['pharaonic', 'laravel-api-response'] as $tag) {
        $this->assertCount(2, ServiceProvider::pathsToPublish(ApiResponseServiceProvider::class, $tag), $tag);
    }

    $this->assertCount(2, ServiceProvider::pathsToPublish(ApiResponseServiceProvider::class));
});
