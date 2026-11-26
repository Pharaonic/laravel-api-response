<?php

use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\ApiResponse\ApiResponseServiceProvider;

test('the config is merged under pharaonic', function () {
    $this->assertSame('default', $this->app['config']->get('pharaonic.api-response.default_profile'));
    $this->assertSame('X-Request-ID', $this->app['config']->get('pharaonic.api-response.request_id.header'));
    $this->assertSame(['api/*'], $this->app['config']->get('pharaonic.api-response.exceptions.paths'));
});

test('the config is published under four tags', function () {
    foreach (['pharaonic', 'laravel-api-response', 'pharaonic-config', 'api-response-config'] as $tag) {
        $paths = ServiceProvider::pathsToPublish(ApiResponseServiceProvider::class, $tag);

        $this->assertCount(1, $paths, $tag);
        $this->assertSame($this->app->configPath('pharaonic/api-response.php'), array_values($paths)[0]);
    }
});

test('nothing else is published', function () {
    $this->assertCount(1, ServiceProvider::pathsToPublish(ApiResponseServiceProvider::class));
});
