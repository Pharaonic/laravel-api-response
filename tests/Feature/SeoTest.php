<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Feature;

use LogicException;
use Pharaonic\Laravel\ApiResponse\Profiles\Profile;
use Pharaonic\Laravel\ApiResponse\Response;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\FakeSeo;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\MobileProfile;
use Pharaonic\Laravel\ApiResponse\Tests\TestCase;

final class SeoTest extends TestCase
{
    private function fakeSeo(): FakeSeo
    {
        $seo = new FakeSeo;
        $this->app->instance('pharaonic-seo', $seo);

        return $seo;
    }

    public function test_seo_is_never_sent_without_with_seo(): void
    {
        $seo = $this->fakeSeo();
        $this->configure(['profiles' => ['default' => Profile::class, 'mobile' => MobileProfile::class]]);

        $this->assertArrayNotHasKey('seo', $this->body($this->finalize(Response::success([1]))));
        $this->assertArrayNotHasKey('seo', $this->body($this->finalize(Response::success([1])->profile('mobile'))));
        $this->assertSame(0, $seo->calls);
    }

    public function test_with_seo_sends_the_tags_once(): void
    {
        $seo = $this->fakeSeo();

        $body = $this->body($this->finalize(Response::success([1])->withSEO()->withSEO()));

        $this->assertSame(['title' => 'Laravel SEO', 'description' => 'Tags'], $body['seo']);
        $this->assertSame(1, $seo->calls);
    }

    public function test_seo_is_read_at_finalization_whatever_the_call_order(): void
    {
        $seo = $this->fakeSeo();
        $builder = Response::success()->withSEO()->message('later');

        $this->assertSame(0, $seo->calls);
        $this->assertSame(['success', 'message', 'seo'], array_keys($this->body($this->finalize($builder))));
    }

    public function test_the_seo_field_follows_the_envelope_names(): void
    {
        $this->fakeSeo();
        $this->configure(['envelope.fields.seo' => 'head']);

        $this->assertArrayHasKey('head', $this->body($this->finalize(Response::success()->withSEO())));
    }

    public function test_with_seo_without_the_package_throws_an_actionable_error(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('composer require pharaonic/laravel-seo');

        $this->finalize(Response::success()->withSEO());
    }

    public function test_the_missing_driver_can_be_ignored(): void
    {
        $this->configure(['seo.missing_driver' => 'ignore']);

        $this->assertSame(['success' => true], $this->body($this->finalize(Response::success()->withSEO())));
    }

    public function test_a_manager_without_to_array_counts_as_missing(): void
    {
        $this->app->instance('pharaonic-seo', new \stdClass);

        $this->expectException(LogicException::class);

        $this->finalize(Response::success()->withSEO());
    }

    public function test_the_real_package_when_it_is_installed(): void
    {
        if (! class_exists('Pharaonic\Laravel\SEO\SEOServiceProvider')) {
            $this->markTestSkipped('pharaonic/laravel-seo is not installed.');
        }

        $this->app->register('Pharaonic\Laravel\SEO\SEOServiceProvider');
        $this->app->make('pharaonic-seo')->title->set('Real title');

        $body = $this->body($this->finalize(Response::success()->withSEO()));

        $this->assertSame('Real title', $body['seo']['title'] ?? null);
    }
}
