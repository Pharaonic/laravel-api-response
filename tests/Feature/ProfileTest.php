<?php

use Illuminate\Http\Request;
use Pharaonic\Laravel\ApiResponse\Response;
use Pharaonic\Laravel\ApiResponse\Support\RequestId;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\ConfiguredProfile;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\FakeSeo;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\MobileProfile;

beforeEach(function () {
    $this->configure([
        'profiles.mobile' => MobileProfile::class,
        'profiles.configured' => ConfiguredProfile::class,
    ]);
});

test('a profile renames fields', function () {
    $body = $this->body($this->build(Response::success(['id' => 1])->profile('mobile')));

    $this->assertSame(['success' => true, 'msg' => null, 'result' => ['id' => 1], 'meta' => null], $body);
});

test('a profile starts from the package defaults', function () {
    $body = $this->body($this->build(Response::error('Nope.')->profile('mobile')->notify('Hi.')));

    $this->assertSame(['success', 'msg', 'code', 'errors', 'meta', 'notifications'], array_keys($body));
});

test('the default profile is configurable', function () {
    $this->configure(['default_profile' => 'mobile']);

    $this->assertArrayHasKey('result', $this->body($this->build(Response::success(1))));
    $this->assertArrayHasKey('data', $this->body($this->build(Response::success(1)->profile('default'))));
});

test('a profile decides whether the body carries the request id', function () {
    $request = Request::create('/api/posts');
    $request->attributes->set(RequestId::ATTRIBUTE, 'abc');

    $this->assertSame('abc', $this->body(Response::success()->toResponse($request))['request_id']);
    $this->assertArrayNotHasKey('request_id', $this->body(Response::success()->profile('mobile')->toResponse($request)));
    $this->assertSame('abc', $this->body(Response::success()->profile('mobile')->withRequestId()->toResponse($request))['request_id']);
});

test('a profile never adds seo tags', function () {
    $this->app->instance('pharaonic-seo', $seo = new FakeSeo);
    $this->app['config']->set('testing.profile_fields', ['seo' => 'meta_tags']);

    foreach (['default', 'mobile', 'configured'] as $profile) {
        $body = $this->body($this->build(Response::success()->profile($profile)));

        $this->assertArrayNotHasKey('seo', $body);
        $this->assertArrayNotHasKey('meta_tags', $body);
    }

    $this->assertSame(0, $seo->calls);
});

test('an unknown profile fails', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('The response profile [tablet] is not defined');

    $this->build(Response::success()->profile('tablet'));
});

test('an unknown default profile fails', function () {
    $this->configure(['default_profile' => 'missing']);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('[missing]');

    $this->build(Response::success());
});

test('a profile can rename the code', function () {
    $this->app['config']->set('testing.profile_fields', ['code' => 'error_code']);

    $body = $this->body($this->build(Response::error('Nope.')->code('x')->profile('configured')));

    $this->assertSame(['success', 'message', 'error_code', 'errors', 'meta'], array_keys($body));
    $this->assertSame('x', $body['error_code']);
});

test('a profile cannot rename an unknown field', function () {
    $this->app['config']->set('testing.profile_fields', ['status' => 'status']);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('unknown field [status]');

    $this->build(Response::success()->profile('configured'));
});

test('a profile cannot send two fields under one name', function () {
    $this->app['config']->set('testing.profile_fields', ['data' => 'message']);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('two fields under the same name');

    $this->build(Response::success()->profile('configured'));
});

test('a field name must be a non empty string', function () {
    $this->app['config']->set('testing.profile_fields', ['data' => '']);

    $this->expectException(InvalidArgumentException::class);

    $this->build(Response::success()->profile('configured'));
});

test('a profile is built by the container', function () {
    $this->app['config']->set('testing.profile_fields', ['data' => 'payload']);

    $this->assertArrayHasKey('payload', $this->body($this->build(Response::success(1)->profile('configured'))));
});

test('a profile must extend the profile class', function () {
    $this->configure(['profiles.wrong' => stdClass::class]);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('must be a class that extends');

    $this->build(Response::success()->profile('wrong'));
});
