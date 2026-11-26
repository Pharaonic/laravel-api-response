<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Feature;

use LogicException;
use Pharaonic\Laravel\ApiResponse\Response;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\FakeSeo;
use Pharaonic\Laravel\ApiResponse\Tests\TestCase;

final class SeoTest extends TestCase
{
    private const MANAGER = 'Pharaonic\Laravel\SEO\Classes\Manager';

    public function test_with_seo_sends_the_tags_of_laravel_seo(): void
    {
        $this->app->instance('pharaonic-seo', $seo = new FakeSeo);

        $body = $this->body($this->build(Response::success(['id' => 1])->withSEO()));

        $this->assertSame(['title' => 'Laravel SEO', 'description' => 'Tags'], $body['seo']);
        $this->assertSame(1, $seo->calls);
    }

    public function test_the_tags_are_read_when_the_response_is_built(): void
    {
        $this->app->instance('pharaonic-seo', $seo = new FakeSeo);

        $response = Response::success()->withSEO();

        $this->assertSame(0, $seo->calls);
        $this->build($response);
        $this->assertSame(1, $seo->calls);
    }

    public function test_no_seo_without_with_seo(): void
    {
        $this->app->instance('pharaonic-seo', $seo = new FakeSeo);

        $this->assertArrayNotHasKey('seo', $this->body($this->build(Response::success())));
        $this->assertArrayNotHasKey('seo', $this->body($this->build(Response::error('Nope.'))));
        $this->assertSame(0, $seo->calls);
    }

    public function test_with_seo_fails_clearly_without_laravel_seo(): void
    {
        if ($this->app->bound('pharaonic-seo')) {
            $this->markTestSkipped('pharaonic/laravel-seo is installed.');
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('composer require pharaonic/laravel-seo');

        $this->build(Response::success()->withSEO());
    }

    public function test_with_seo_reads_the_real_package(): void
    {
        if (! class_exists(self::MANAGER)) {
            $this->markTestSkipped('pharaonic/laravel-seo is not installed.');
        }

        $this->app->register('Pharaonic\Laravel\SEO\SEOServiceProvider');
        $manager = $this->app->make('pharaonic-seo');
        $this->assertIsObject($manager);
        $export = [$manager, 'toArray'];
        $this->assertIsCallable($export);

        $body = $this->body($this->build(Response::success()->withSEO()));

        $this->assertSame($export(), $body['seo']);
    }
}
