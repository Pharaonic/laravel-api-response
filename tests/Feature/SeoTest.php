<?php

use Pharaonic\Laravel\ApiResponse\Response;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\FakeSeo;

test('with seo sends the tags of laravel seo', function () {
    $this->app->instance('pharaonic-seo', $seo = new FakeSeo);

    $body = $this->body($this->build(Response::success(['id' => 1])->withSEO()));

    $this->assertSame(['title' => 'Laravel SEO', 'description' => 'Tags'], $body['seo']);
    $this->assertSame(1, $seo->calls);
});

test('the tags are read when the response is built', function () {
    $this->app->instance('pharaonic-seo', $seo = new FakeSeo);

    $response = Response::success()->withSEO();

    $this->assertSame(0, $seo->calls);
    $this->build($response);
    $this->assertSame(1, $seo->calls);
});

test('no seo without with seo', function () {
    $this->app->instance('pharaonic-seo', $seo = new FakeSeo);

    $this->assertArrayNotHasKey('seo', $this->body($this->build(Response::success())));
    $this->assertArrayNotHasKey('seo', $this->body($this->build(Response::error('Nope.'))));
    $this->assertSame(0, $seo->calls);
});

test('with seo fails clearly without laravel seo', function () {
    if ($this->app->bound('pharaonic-seo')) {
        $this->markTestSkipped('pharaonic/laravel-seo is installed.');
    }

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('composer require pharaonic/laravel-seo');

    $this->build(Response::success()->withSEO());
});

test('with seo reads the real package', function () {
    if (! class_exists('Pharaonic\Laravel\SEO\Classes\Manager')) {
        $this->markTestSkipped('pharaonic/laravel-seo is not installed.');
    }

    $this->app->register('Pharaonic\Laravel\SEO\SEOServiceProvider');
    $manager = $this->app->make('pharaonic-seo');
    $this->assertIsObject($manager);
    $export = [$manager, 'toArray'];
    $this->assertIsCallable($export);

    $body = $this->body($this->build(Response::success()->withSEO()));

    $this->assertSame($export(), $body['seo']);
});
