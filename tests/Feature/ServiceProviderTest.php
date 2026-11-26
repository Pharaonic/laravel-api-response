<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Feature;

use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\ApiResponse\ApiResponseServiceProvider;
use Pharaonic\Laravel\ApiResponse\Tests\TestCase;

final class ServiceProviderTest extends TestCase
{
    public function test_the_config_is_merged_under_pharaonic(): void
    {
        $this->assertSame('default', $this->app['config']->get('pharaonic.api-response.default_profile'));
        $this->assertSame('X-Request-ID', $this->app['config']->get('pharaonic.api-response.request_id.header'));
        $this->assertSame(['api/*'], $this->app['config']->get('pharaonic.api-response.exceptions.paths'));
    }

    public function test_the_config_is_published_under_four_tags(): void
    {
        foreach (['pharaonic', 'laravel-api-response', 'pharaonic-config', 'api-response-config'] as $tag) {
            $paths = ServiceProvider::pathsToPublish(ApiResponseServiceProvider::class, $tag);

            $this->assertCount(1, $paths, $tag);
            $this->assertSame($this->app->configPath('pharaonic/api-response.php'), array_values($paths)[0]);
        }
    }

    public function test_nothing_else_is_published(): void
    {
        $this->assertCount(1, ServiceProvider::pathsToPublish(ApiResponseServiceProvider::class));
    }
}
