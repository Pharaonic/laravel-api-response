<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Feature;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Pharaonic\Laravel\ApiResponse\Response;
use Pharaonic\Laravel\ApiResponse\Support\RequestId;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\ConfiguredProfile;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\FakeSeo;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\MobileProfile;
use Pharaonic\Laravel\ApiResponse\Tests\TestCase;
use stdClass;

final class ProfileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->configure([
            'profiles.mobile' => MobileProfile::class,
            'profiles.configured' => ConfiguredProfile::class,
        ]);
    }

    public function test_a_profile_renames_fields(): void
    {
        $body = $this->body($this->build(Response::success(['id' => 1])->profile('mobile')));

        $this->assertSame(['success' => true, 'msg' => null, 'result' => ['id' => 1], 'meta' => null], $body);
    }

    public function test_a_profile_starts_from_the_package_defaults(): void
    {
        $body = $this->body($this->build(Response::error('Nope.')->profile('mobile')->notify('Hi.')));

        $this->assertSame(['success', 'msg', 'errors', 'meta', 'notifications'], array_keys($body));
    }

    public function test_the_default_profile_is_configurable(): void
    {
        $this->configure(['default_profile' => 'mobile']);

        $this->assertArrayHasKey('result', $this->body($this->build(Response::success(1))));
        $this->assertArrayHasKey('data', $this->body($this->build(Response::success(1)->profile('default'))));
    }

    public function test_a_profile_decides_whether_the_body_carries_the_request_id(): void
    {
        $request = Request::create('/api/posts');
        $request->attributes->set(RequestId::ATTRIBUTE, 'abc');

        $this->assertSame('abc', $this->body(Response::success()->toResponse($request))['request_id']);
        $this->assertArrayNotHasKey('request_id', $this->body(Response::success()->profile('mobile')->toResponse($request)));
        $this->assertSame('abc', $this->body(Response::success()->profile('mobile')->withRequestId()->toResponse($request))['request_id']);
    }

    public function test_a_profile_never_adds_seo_tags(): void
    {
        $this->app->instance('pharaonic-seo', $seo = new FakeSeo);
        $this->app['config']->set('testing.profile_fields', ['seo' => 'meta_tags']);

        foreach (['default', 'mobile', 'configured'] as $profile) {
            $body = $this->body($this->build(Response::success()->profile($profile)));

            $this->assertArrayNotHasKey('seo', $body);
            $this->assertArrayNotHasKey('meta_tags', $body);
        }

        $this->assertSame(0, $seo->calls);
    }

    public function test_an_unknown_profile_fails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The response profile [tablet] is not defined');

        $this->build(Response::success()->profile('tablet'));
    }

    public function test_an_unknown_default_profile_fails(): void
    {
        $this->configure(['default_profile' => 'missing']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[missing]');

        $this->build(Response::success());
    }

    public function test_a_profile_cannot_rename_an_unknown_field(): void
    {
        $this->app['config']->set('testing.profile_fields', ['code' => 'code']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown field [code]');

        $this->build(Response::success()->profile('configured'));
    }

    public function test_a_profile_cannot_send_two_fields_under_one_name(): void
    {
        $this->app['config']->set('testing.profile_fields', ['data' => 'message']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('two fields under the same name');

        $this->build(Response::success()->profile('configured'));
    }

    public function test_a_field_name_must_be_a_non_empty_string(): void
    {
        $this->app['config']->set('testing.profile_fields', ['data' => '']);

        $this->expectException(InvalidArgumentException::class);

        $this->build(Response::success()->profile('configured'));
    }

    public function test_a_profile_is_built_by_the_container(): void
    {
        $this->app['config']->set('testing.profile_fields', ['data' => 'payload']);

        $this->assertArrayHasKey('payload', $this->body($this->build(Response::success(1)->profile('configured'))));
    }

    public function test_a_profile_must_extend_the_profile_class(): void
    {
        $this->configure(['profiles.wrong' => stdClass::class]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a class that extends');

        $this->build(Response::success()->profile('wrong'));
    }
}
