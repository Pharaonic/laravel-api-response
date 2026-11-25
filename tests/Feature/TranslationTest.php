<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Pharaonic\Laravel\ApiResponse\Response;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\ApiExceptionHandler;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\Post;
use Pharaonic\Laravel\ApiResponse\Tests\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

final class TranslationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(ExceptionHandler::class, new ApiExceptionHandler($this->app));
    }

    private function throwing(string $uri, Throwable $exception): void
    {
        $this->app['router']->get($uri, function () use ($exception) {
            throw $exception;
        });
    }

    public function test_the_status_helpers_send_the_messages_of_the_current_locale(): void
    {
        $this->app->setLocale('ar');

        $this->assertSame('غير مُصادَق عليه.', $this->body($this->finalize(Response::unauthorized()))['message']);
        $this->assertSame('غير مصرح لك بتنفيذ هذا الإجراء.', $this->body($this->finalize(Response::forbidden()))['message']);
        $this->assertSame('غير موجود.', $this->body($this->finalize(Response::notFound()))['message']);
        $this->assertSame('البيانات المُرسلة غير صالحة.', $this->body($this->finalize(Response::validationError([])))['message']);
    }

    public function test_a_message_the_caller_gives_is_never_translated(): void
    {
        $this->app->setLocale('ar');

        $this->assertSame('Post missing.', $this->body($this->finalize(Response::notFound('Post missing.')))['message']);
    }

    public function test_a_locale_without_lines_falls_back_to_english(): void
    {
        $this->app->setLocale('xx');

        $this->assertSame('Not Found.', $this->body($this->finalize(Response::notFound()))['message']);
    }

    public function test_the_application_can_override_a_line(): void
    {
        $this->app['translator']->addLines(['messages.not_found' => 'Nothing here.'], 'en', 'api-response');

        $this->assertSame('Nothing here.', $this->body($this->finalize(Response::notFound()))['message']);
    }

    public function test_exceptions_are_rendered_in_the_current_locale_with_stable_codes(): void
    {
        $this->app['config']->set('app.locale', 'ar');
        $this->posts(0);
        $this->app['router']->get('api/model', fn () => Post::query()->findOrFail(99));
        $this->app['router']->get('api/validate', fn (Request $request) => $request->validate(['email' => 'required']));
        $this->throwing('api/auth', new AuthenticationException);
        $this->throwing('api/forbidden', new AuthorizationException);
        $this->throwing('api/csrf', new TokenMismatchException);
        $this->throwing('api/throttle', new ThrottleRequestsException('Too Many Attempts.'));
        $this->throwing('api/unavailable', new HttpException(503));
        $this->throwing('api/boom', new RuntimeException('secret'));

        $cases = [
            'api/model' => [404, 'NOT_FOUND', 'غير موجود.'],
            'api/missing-route' => [404, 'NOT_FOUND', 'غير موجود'],
            'api/validate' => [422, 'VALIDATION_ERROR', 'البيانات المُرسلة غير صالحة.'],
            'api/auth' => [401, 'UNAUTHENTICATED', 'غير مُصادَق عليه.'],
            'api/forbidden' => [403, 'FORBIDDEN', 'غير مصرح لك بتنفيذ هذا الإجراء.'],
            'api/csrf' => [419, 'PAGE_EXPIRED', 'رمز CSRF غير متطابق.'],
            'api/throttle' => [429, 'TOO_MANY_REQUESTS', 'محاولات كثيرة جدًا.'],
            'api/unavailable' => [503, 'SERVICE_UNAVAILABLE', 'الخدمة غير متاحة'],
            'api/boom' => [500, 'SERVER_ERROR', 'خطأ في الخادم.'],
        ];

        foreach ($cases as $uri => [$status, $code, $message]) {
            $this->getJson($uri)->assertStatus($status)->assertJson(['message' => $message, 'code' => $code]);
        }
    }

    public function test_an_exception_message_of_the_application_is_kept(): void
    {
        $this->app['config']->set('app.locale', 'ar');
        $this->throwing('api/auth', new AuthenticationException('Token expired.'));
        $this->throwing('api/conflict', new HttpException(409, 'Already exists.'));

        $this->getJson('api/auth')->assertJson(['message' => 'Token expired.']);
        $this->getJson('api/conflict')->assertJson(['message' => 'Already exists.']);
    }
}
