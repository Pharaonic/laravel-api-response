<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Feature;

use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\ApiResponse\ApiResponseServiceProvider;
use Pharaonic\Laravel\ApiResponse\Tests\TestCase;

final class ServiceProviderTest extends TestCase
{
    public function test_it_registers_the_service_provider(): void
    {
        $this->assertNotEmpty($this->app->getProviders(ApiResponseServiceProvider::class));
    }

    public function test_it_merges_the_package_configuration(): void
    {
        $this->assertSame('default', config('pharaonic.api-response.default_profile'));
        $this->assertSame(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES, config('pharaonic.api-response.json_options'));
    }

    public function test_it_publishes_the_configuration_under_the_four_tags(): void
    {
        $target = $this->app->configPath('pharaonic/api-response.php');

        foreach (['pharaonic', 'laravel-api-response', 'pharaonic-config', 'api-response-config'] as $tag) {
            $this->assertContains($target, ServiceProvider::pathsToPublish(ApiResponseServiceProvider::class, $tag), $tag);
        }
    }

    public function test_it_publishes_the_translations_under_the_four_tags(): void
    {
        $target = $this->app->resourcePath('lang/vendor/api-response');

        foreach (['pharaonic', 'laravel-api-response', 'pharaonic-translations', 'api-response-translations'] as $tag) {
            $this->assertContains($target, ServiceProvider::pathsToPublish(ApiResponseServiceProvider::class, $tag), $tag);
        }
    }

    public function test_every_locale_has_the_english_keys(): void
    {
        $english = require __DIR__.'/../../resources/lang/en/messages.php';

        foreach (glob(__DIR__.'/../../resources/lang/*/messages.php') ?: [] as $file) {
            $lines = require $file;

            $this->assertSame(array_keys($english), array_keys($lines), $file);
            $this->assertSame(array_keys($english['status']), array_keys($lines['status']), $file);
        }
    }
}
