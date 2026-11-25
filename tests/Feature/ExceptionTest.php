<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Pharaonic\Laravel\ApiResponse\ExceptionRenderer;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\ApiExceptionHandler;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\CodedHttpException;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\InsufficientBalance;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\InsufficientBalanceMapper;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\OwnRenderException;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\OwnRenderMapper;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\Post;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\RenderingCodedHttpException;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\RuntimeMapper;
use Pharaonic\Laravel\ApiResponse\Tests\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

final class ExceptionTest extends TestCase
{
    private ApiExceptionHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new ApiExceptionHandler($this->app);
        $this->app->instance(ExceptionHandler::class, $this->handler);
    }

    private function throwing(string $uri, Throwable $exception): void
    {
        $this->app['router']->get($uri, function () use ($exception) {
            throw $exception;
        });
        $this->app['router']->post($uri, fn () => 'post only');
    }

    public function test_validation_exceptions_keep_their_error_bag(): void
    {
        $this->app['router']->get('api/validate', fn (Request $request) => $request->validate(['email' => 'required|email']));

        $this->getJson('api/validate?email=nope')
            ->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'message' => 'The given data was invalid.',
                'code' => 'VALIDATION_ERROR',
                'errors' => ['email' => ['The email must be a valid email address.']],
            ]);
    }

    public function test_the_standard_exceptions_are_mapped(): void
    {
        $this->posts(0);
        $this->app['router']->get('api/model', fn () => Post::query()->findOrFail(99));

        $cases = [
            ['api/auth', new AuthenticationException, 401, 'UNAUTHENTICATED', 'Unauthenticated.'],
            ['api/forbidden', new AuthorizationException, 403, 'FORBIDDEN', 'This action is unauthorized.'],
            ['api/conflict', new HttpException(409, 'Already exists.'), 409, 'CONFLICT', 'Already exists.'],
            ['api/teapot', new HttpException(418), 418, 'I_M_A_TEAPOT', "I'm a teapot"],
        ];

        foreach ($cases as [$uri, $exception, $status, $code, $message]) {
            $this->throwing($uri, $exception);

            $this->getJson($uri)->assertStatus($status)->assertExactJson(['success' => false, 'message' => $message, 'code' => $code]);
        }

        $this->getJson('api/model')->assertStatus(404)->assertExactJson(['success' => false, 'message' => 'Not Found.', 'code' => 'NOT_FOUND']);
        $this->getJson('api/missing-route')->assertStatus(404)->assertJson(['code' => 'NOT_FOUND']);
    }

    public function test_the_model_name_never_leaks(): void
    {
        $this->posts(0);
        $this->app['router']->get('api/model', fn () => Post::query()->findOrFail(99));

        $this->assertStringNotContainsString('Post', (string) $this->getJson('api/model')->getContent());
    }

    public function test_debug_mode_names_the_missing_model(): void
    {
        $this->app['config']->set('app.debug', true);
        $this->posts(0);
        $this->app['router']->get('api/model', fn () => Post::query()->findOrFail(99));

        $response = $this->getJson('api/model')->assertStatus(404)->assertJson(['message' => 'Not Found.', 'code' => 'NOT_FOUND']);
        $debug = $response->json('meta.debug');

        $this->assertSame(Post::class, $debug['model']);
        $this->assertSame([99], $debug['ids']);
        $this->assertSame(ModelNotFoundException::class, $debug['exception']);
        $this->assertArrayNotHasKey('args', $debug['trace'][0]);
    }

    public function test_http_codes_are_fixed_whatever_the_status_text(): void
    {
        $this->throwing('api/large', new HttpException(413));
        $this->throwing('api/unprocessable', new HttpException(422));
        $this->throwing('api/internal', new HttpException(500));
        $this->throwing('api/custom', new HttpException(499));

        $this->getJson('api/large')->assertStatus(413)->assertJson(['code' => 'CONTENT_TOO_LARGE', 'message' => 'Content Too Large']);
        $this->getJson('api/unprocessable')->assertStatus(422)->assertJson(['code' => 'UNPROCESSABLE_CONTENT']);
        $this->getJson('api/internal')->assertStatus(500)->assertJson(['code' => 'SERVER_ERROR']);
        $this->getJson('api/custom')->assertStatus(499)->assertJson(['code' => 'HTTP_499']);
    }

    public function test_original_headers_are_kept(): void
    {
        $this->throwing('api/throttle', new ThrottleRequestsException('Too Many Attempts.', null, ['Retry-After' => 60, 'X-RateLimit-Limit' => 1]));
        $this->throwing('api/challenge', new HttpException(401, 'Login.', null, ['WWW-Authenticate' => 'Bearer']));

        $this->getJson('api/throttle')
            ->assertStatus(429)
            ->assertHeader('Retry-After', '60')
            ->assertHeader('X-RateLimit-Limit', '1')
            ->assertJson(['code' => 'TOO_MANY_REQUESTS', 'message' => 'Too Many Attempts.']);

        $this->getJson('api/challenge')->assertStatus(401)->assertHeader('WWW-Authenticate', 'Bearer')->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    public function test_method_not_allowed_keeps_its_allow_header(): void
    {
        $this->throwing('api/only', new MethodNotAllowedHttpException(['POST']));

        $this->getJson('api/only')->assertStatus(405)->assertHeader('Allow', 'POST')->assertJson(['code' => 'METHOD_NOT_ALLOWED']);
    }

    public function test_unexpected_exceptions_are_safe_in_production(): void
    {
        $this->throwing('api/boom', new RuntimeException('SQLSTATE secret in /var/www/app.php'));

        $response = $this->getJson('api/boom');

        $response->assertStatus(500)->assertExactJson(['success' => false, 'message' => 'Server Error.', 'code' => 'SERVER_ERROR']);
        $this->assertStringNotContainsString('secret', (string) $response->getContent());
    }

    public function test_debug_mode_adds_the_exception_details(): void
    {
        $this->app['config']->set('app.debug', true);
        $this->throwing('api/boom', new RuntimeException('Visible in debug.'));

        $debug = $this->getJson('api/boom')->assertStatus(500)->json('meta.debug');

        $this->assertSame(RuntimeException::class, $debug['exception']);
        $this->assertSame('Visible in debug.', $debug['message']);
        $this->assertIsArray($debug['trace']);
        $this->assertArrayNotHasKey('args', $debug['trace'][0]);
    }

    public function test_laravel_still_reports_each_exception_once(): void
    {
        $this->throwing('api/boom', new RuntimeException('Report me.'));
        $this->throwing('api/auth', new AuthenticationException);

        $this->getJson('api/boom');
        $this->getJson('api/auth');

        $this->assertSame([RuntimeException::class, AuthenticationException::class], $this->handler->reported);
    }

    public function test_web_requests_are_left_to_laravel(): void
    {
        $this->throwing('web/page', new HttpException(409, 'Conflict page.'));

        $response = $this->get('web/page');

        $response->assertStatus(409);
        $this->assertStringNotContainsString('"success"', (string) $response->getContent());
    }

    public function test_configured_paths_are_rendered_even_without_accept_json(): void
    {
        $this->throwing('api/plain', new HttpException(409, 'Conflict.'));

        $this->get('api/plain')->assertStatus(409)->assertJson(['success' => false, 'code' => 'CONFLICT']);

        $this->configure(['exceptions.paths' => []]);
        $this->assertStringNotContainsString('"success"', (string) $this->get('api/plain')->getContent());
        $this->getJson('api/plain')->assertJson(['success' => false]);
    }

    public function test_exceptions_with_their_own_response_are_left_to_laravel(): void
    {
        $this->throwing('api/response', new HttpResponseException(response('own', 202)));
        $this->app['router']->get('api/redirect', function () {
            Validator::make([], ['a' => 'required'])->validate();
        });

        $this->assertSame('own', $this->getJson('api/response')->assertStatus(202)->getContent());
        $this->assertNull((new ExceptionRenderer)(new HttpResponseException(response('x')), Request::create('/api/x')));
    }

    public function test_an_http_exception_sends_its_own_error_code(): void
    {
        $this->throwing('api/version', new CodedHttpException(400, 'Unknown API version.', 'UNKNOWN_API_VERSION', ['Vary' => 'Accept']));
        $this->throwing('api/invalid-code', new CodedHttpException(400, 'x', "bad\ncode"));

        $this->getJson('api/version')
            ->assertStatus(400)
            ->assertHeader('Vary', 'Accept')
            ->assertExactJson(['success' => false, 'message' => 'Unknown API version.', 'code' => 'UNKNOWN_API_VERSION']);
        $this->getJson('api/invalid-code')->assertStatus(400)->assertJson(['code' => 'BAD_REQUEST']);
    }

    public function test_an_exception_that_translates_itself_sends_its_localized_message(): void
    {
        $this->throwing('api/localized', new CodedHttpException(400, 'Unknown API version.', 'UNKNOWN_API_VERSION', [], 'إصدار الـ API غير معروف.'));

        $this->getJson('api/localized')->assertStatus(400)->assertExactJson(['success' => false, 'message' => 'إصدار الـ API غير معروف.', 'code' => 'UNKNOWN_API_VERSION']);
    }

    public function test_an_http_exception_with_an_error_code_is_rendered_in_the_envelope_despite_render(): void
    {
        $this->throwing('api/idempotency', new RenderingCodedHttpException(409, 'Still processing.', 'idempotency_in_progress', ['Retry-After' => '5']));

        $this->getJson('api/idempotency')
            ->assertStatus(409)
            ->assertHeader('Retry-After', '5')
            ->assertExactJson(['success' => false, 'message' => 'Still processing.', 'code' => 'IDEMPOTENCY_IN_PROGRESS']);
    }

    public function test_an_http_exception_with_an_error_code_is_rendered_for_any_request(): void
    {
        $this->throwing('checkout', new RenderingCodedHttpException(400, 'Key required.', 'idempotency_key_missing'));
        $this->throwing('web/plain', new HttpException(400));

        $this->get('checkout', ['Accept' => 'text/html'])
            ->assertStatus(400)
            ->assertExactJson(['success' => false, 'message' => 'Key required.', 'code' => 'IDEMPOTENCY_KEY_MISSING']);
        $this->assertNotSame('application/json', $this->get('web/plain', ['Accept' => 'text/html'])->headers->get('Content-Type'));
    }

    public function test_other_exceptions_with_render_are_left_to_laravel_unless_mapped(): void
    {
        $this->throwing('api/own', new OwnRenderException);

        $this->assertSame('own render', $this->getJson('api/own')->assertStatus(418)->getContent());

        $this->app['config']->set('pharaonic.api-response.exceptions.mappers', [OwnRenderException::class => OwnRenderMapper::class]);

        $this->getJson('api/own')->assertStatus(409)->assertExactJson(['success' => false, 'message' => 'Mapped.', 'code' => 'MAPPED']);
    }

    public function test_a_custom_mapper_turns_a_domain_exception_into_a_response(): void
    {
        $this->configure(['exceptions.mappers' => [InsufficientBalance::class => InsufficientBalanceMapper::class]]);
        $this->throwing('api/pay', new InsufficientBalance(12));

        $this->getJson('api/pay')->assertStatus(422)->assertExactJson([
            'success' => false,
            'message' => 'Insufficient balance.',
            'code' => 'INSUFFICIENT_BALANCE',
            'meta' => ['balance' => 12],
        ]);
    }

    public function test_the_most_specific_mapper_wins(): void
    {
        $this->configure(['exceptions.mappers' => [
            RuntimeException::class => RuntimeMapper::class,
            InsufficientBalance::class => InsufficientBalanceMapper::class,
        ]]);
        $this->throwing('api/pay', new InsufficientBalance(1));
        $this->throwing('api/runtime', new RuntimeException('x'));

        $this->getJson('api/pay')->assertJson(['code' => 'INSUFFICIENT_BALANCE']);
        $this->getJson('api/runtime')->assertJson(['code' => 'RUNTIME']);
    }

    public function test_a_mapper_returning_null_falls_back_to_the_next_mapping(): void
    {
        $this->configure(['exceptions.mappers' => [
            Throwable::class => InsufficientBalanceMapper::class,
        ]]);
        $this->throwing('api/auth', new AuthenticationException);

        $this->getJson('api/auth')->assertStatus(401)->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    public function test_invalid_mappers_are_refused(): void
    {
        $this->configure(['exceptions.mappers' => [RuntimeException::class => \stdClass::class]]);

        $this->expectException(\InvalidArgumentException::class);

        (new ExceptionRenderer)(new RuntimeException('x'), Request::create('/api/x', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']));
    }

    public function test_the_renderer_uses_the_response_profile_and_request_id(): void
    {
        $request = Request::create('/api/x', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $request->attributes->set('pharaonic.api-response.request_id', 'abc');

        $response = (new ExceptionRenderer)(new AuthenticationException, $request);

        $this->assertNotNull($response);
        $this->assertSame('abc', $this->body($response)['request_id']);
    }
}
