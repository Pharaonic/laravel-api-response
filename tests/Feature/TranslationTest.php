<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\TokenMismatchException;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\Post;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->useApiExceptionHandler();
    $this->posts(0);

    $this->throwing('api/crash', new RuntimeException('Boom.'));
    $this->throwing('api/auth', new AuthenticationException);
    $this->throwing('api/forbidden', new AuthorizationException);
    $this->throwing('api/csrf', new TokenMismatchException);
    $this->throwing('api/unavailable', new HttpException(503));
    $this->throwing('api/own', new HttpException(409, 'Order already placed.'));
    $this->app['router']->get('api/model', fn () => Post::query()->findOrFail(1));
    $this->app['router']->get('api/validate', fn (Request $request) => $request->validate(['email' => 'required']));
    $this->app['router']->get('api/limited', fn () => 'ok')->middleware(ThrottleRequests::class.':1,1');
});

test('the messages are english by default', function () {
    $this->getJson('api/crash')->assertJson(['message' => 'Server Error.']);
    $this->getJson('api/model')->assertJson(['message' => 'Not Found.']);
    $this->getJson('api/unavailable')->assertJson(['message' => 'Service Unavailable']);
});

test('the messages follow the application locale', function () {
    $this->app->setLocale('ar');

    $this->getJson('api/crash')->assertJson(['message' => 'خطأ في الخادم.']);
    $this->getJson('api/model')->assertJson(['message' => 'غير موجود.']);
    $this->getJson('api/auth')->assertJson(['message' => 'غير مصادق عليه.']);
    $this->getJson('api/forbidden')->assertJson(['message' => 'غير مصرح لك بتنفيذ هذا الإجراء.']);
    $this->getJson('api/csrf')->assertJson(['message' => 'رمز CSRF غير متطابق.']);
    $this->getJson('api/unavailable')->assertJson(['message' => 'الخدمة غير متاحة']);
    // From Laravel 9 the message is the first validation error, translated by Laravel's own validation lines.
    $this->getJson('api/validate')->assertStatus(422)->assertJson(['message' => 'The email field is required.']);

    $this->getJson('api/limited')->assertOk();
    $this->getJson('api/limited')->assertStatus(429)->assertJson(['message' => 'محاولات كثيرة جدًا.']);
});

test('the application own messages are kept', function () {
    $this->app->setLocale('ar');

    $this->getJson('api/own')->assertJson(['message' => 'Order already placed.']);
});

test('a locale without the lines falls back to english', function () {
    $this->app->setLocale('fr');

    $this->getJson('api/crash')->assertJson(['message' => 'Server Error.']);
    $this->getJson('api/unavailable')->assertJson(['message' => 'Service Unavailable']);
});

test('the application can override a line', function () {
    $this->app['translator']->addLines(['messages.server_error' => 'Something broke.'], 'en', 'api-response');

    $this->getJson('api/crash')->assertJson(['message' => 'Something broke.']);
});

test('every status line has an arabic translation', function () {
    $en = require __DIR__.'/../../resources/lang/en/messages.php';
    $ar = require __DIR__.'/../../resources/lang/ar/messages.php';

    $this->assertSame(array_keys($en), array_keys($ar));
    $this->assertSame(array_keys($en['status']), array_keys($ar['status']));
});
