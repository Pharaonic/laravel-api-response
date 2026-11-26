<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\TokenMismatchException;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\ApiExceptionHandler;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\ConfiguredProfile;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\OwnRenderException;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\Post;
use Pharaonic\Laravel\ApiResponse\Tests\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
        $this->app['router']->get($uri, function () use ($exception): void {
            throw $exception;
        });
    }

    public function test_a_validation_exception_sends_422_with_its_errors(): void
    {
        $this->app['router']->get('api/validate', fn (Request $request) => $request->validate(['email' => 'required|email']));

        $this->getJson('api/validate?email=nope')
            ->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => ['email' => ['The email must be a valid email address.']],
                'meta' => null,
            ]);
    }

    public function test_laravel_exceptions_get_their_status(): void
    {
        $this->posts(0);
        $this->app['router']->get('api/model', fn () => Post::query()->findOrFail(99));

        $cases = [
            ['api/auth', new AuthenticationException, 401, 'Unauthenticated.'],
            ['api/forbidden', new AuthorizationException, 403, 'This action is unauthorized.'],
            ['api/forbidden-message', new AuthorizationException('Not your post.'), 403, 'Not your post.'],
            ['api/csrf', new TokenMismatchException, 419, 'CSRF token mismatch.'],
            ['api/conflict', new HttpException(409, 'Already exists.'), 409, 'Already exists.'],
            ['api/teapot', new HttpException(418), 418, "I'm a teapot"],
            ['api/unavailable', new HttpException(503), 503, 'Service Unavailable'],
        ];

        foreach ($cases as [$uri, $exception, $status, $message]) {
            $this->throwing($uri, $exception);
        }

        foreach ($cases as [$uri, , $status, $message]) {
            $this->getJson($uri)->assertStatus($status)->assertExactJson(['success' => false, 'message' => $message, 'errors' => null, 'meta' => null]);
        }

        $this->getJson('api/model')->assertStatus(404)->assertExactJson(['success' => false, 'message' => 'Not Found.', 'errors' => null, 'meta' => null]);
    }

    public function test_a_missing_route_sends_404(): void
    {
        $this->getJson('api/missing')->assertStatus(404)->assertJson(['success' => false, 'errors' => null, 'meta' => null]);
    }

    public function test_a_model_not_found_never_names_the_model(): void
    {
        $this->posts(0);
        $this->app['router']->get('api/model', fn () => Post::query()->findOrFail(99));

        $content = (string) $this->getJson('api/model')->getContent();

        $this->assertStringNotContainsString('Post', $content);
        $this->assertStringNotContainsString('99', $content);
    }

    public function test_a_wrong_method_keeps_the_allow_header(): void
    {
        $this->app['router']->get('api/posts', fn () => 'ok');

        $response = $this->postJson('api/posts');

        $response->assertStatus(405)->assertJson(['success' => false]);
        $this->assertSame('GET, HEAD', $response->headers->get('Allow'));
    }

    public function test_throttling_sends_429_with_its_headers(): void
    {
        $this->app['router']->get('api/limited', fn () => 'ok')->middleware(ThrottleRequests::class.':1,1');

        $this->getJson('api/limited')->assertOk();

        $response = $this->getJson('api/limited');

        $response->assertStatus(429)->assertExactJson(['success' => false, 'message' => 'Too Many Attempts.', 'errors' => null, 'meta' => null]);
        $this->assertNotNull($response->headers->get('Retry-After'));
        $this->assertSame('0', $response->headers->get('X-RateLimit-Remaining'));
    }

    public function test_an_http_exception_keeps_its_headers(): void
    {
        $this->throwing('api/challenge', new HttpException(401, 'Token expired.', null, ['WWW-Authenticate' => 'Bearer']));

        $response = $this->getJson('api/challenge');

        $response->assertStatus(401)->assertJson(['message' => 'Token expired.']);
        $this->assertSame('Bearer', $response->headers->get('WWW-Authenticate'));
    }

    public function test_an_unexpected_exception_sends_a_bare_500_in_production(): void
    {
        $this->throwing('api/crash', new RuntimeException('SQLSTATE secret at /var/www/app.php'));

        $response = $this->getJson('api/crash');
        $content = (string) $response->getContent();

        $response->assertStatus(500)->assertExactJson(['success' => false, 'message' => 'Server Error.', 'errors' => null, 'meta' => null]);
        $this->assertStringNotContainsString('SQLSTATE', $content);
        $this->assertStringNotContainsString('/var/www', $content);
        $this->assertStringNotContainsString('trace', $content);
    }

    public function test_an_unexpected_exception_is_left_to_laravel_in_debug_mode(): void
    {
        $this->app['config']->set('app.debug', true);
        $this->throwing('api/crash', new RuntimeException('Visible while debugging.'));

        $response = $this->getJson('api/crash');

        $response->assertStatus(500)->assertJsonMissing(['success' => false]);
        $this->assertStringContainsString('Visible while debugging.', (string) $response->getContent());
    }

    public function test_exceptions_are_still_reported_once(): void
    {
        $this->throwing('api/crash', new RuntimeException('Boom.'));

        $this->getJson('api/crash')->assertStatus(500);

        $this->assertSame([RuntimeException::class], $this->handler->reported);
    }

    public function test_a_path_in_exceptions_paths_is_rendered_without_accept_json(): void
    {
        $this->throwing('api/conflict', new HttpException(409, 'Already exists.'));

        $this->get('api/conflict')->assertStatus(409)->assertExactJson(['success' => false, 'message' => 'Already exists.', 'errors' => null, 'meta' => null]);
    }

    public function test_a_web_page_keeps_laravel_rendering(): void
    {
        $this->throwing('page', new HttpException(409, 'Already exists.'));

        $response = $this->get('page');

        $response->assertStatus(409);
        $this->assertStringNotContainsString('"success"', (string) $response->getContent());
    }

    public function test_a_web_page_that_expects_json_is_rendered(): void
    {
        $this->throwing('page', new HttpException(409, 'Already exists.'));

        $this->getJson('page')->assertStatus(409)->assertJson(['success' => false]);
    }

    public function test_without_paths_only_json_requests_are_rendered(): void
    {
        $this->configure(['exceptions.paths' => []]);
        $this->throwing('api/conflict', new HttpException(409, 'Already exists.'));

        $this->assertStringNotContainsString('"success"', (string) $this->get('api/conflict')->getContent());
        $this->getJson('api/conflict')->assertJson(['success' => false]);
    }

    public function test_exceptions_with_their_own_response_are_left_to_laravel(): void
    {
        $this->throwing('api/own', new OwnRenderException);
        $this->throwing('api/http-response', new HttpResponseException(new JsonResponse(['raw' => true], 418)));

        $this->getJson('api/own')->assertStatus(409)->assertExactJson(['own' => true]);
        $this->getJson('api/http-response')->assertStatus(418)->assertExactJson(['raw' => true]);
    }

    public function test_errors_follow_the_default_profile(): void
    {
        $this->configure([
            'profiles.configured' => ConfiguredProfile::class,
            'default_profile' => 'configured',
        ]);
        $this->app['config']->set('testing.profile_fields', ['message' => 'msg', 'errors' => 'problems']);
        $this->throwing('api/conflict', new HttpException(409, 'Already exists.'));

        $this->getJson('api/conflict')->assertExactJson(['success' => false, 'msg' => 'Already exists.', 'problems' => null, 'meta' => null]);
    }

    public function test_package_messages_follow_the_application_translations(): void
    {
        $this->app['translator']->getLoader()->addJsonPath(__DIR__.'/../Fixtures/lang');
        $this->app->setLocale('fr');
        $this->throwing('api/crash', new RuntimeException('Boom.'));

        $this->getJson('api/crash')->assertJson(['message' => 'Erreur du serveur.']);
    }

    public function test_only_an_http_exception_is_read_through_its_previous_exception(): void
    {
        $this->throwing('api/wrapped', new RuntimeException('Failed.', 0, new ModelNotFoundException));

        $this->getJson('api/wrapped')->assertStatus(500)->assertJson(['message' => 'Server Error.']);
    }

    public function test_a_prepared_http_exception_is_read_through_its_cause(): void
    {
        $this->throwing('api/model', new NotFoundHttpException('No query results for model [App\\Post] 1', new ModelNotFoundException));
        $this->throwing('api/csrf', new HttpException(419, '', new TokenMismatchException));

        $this->getJson('api/model')->assertStatus(404)->assertJson(['message' => 'Not Found.']);
        $this->getJson('api/csrf')->assertStatus(419)->assertJson(['message' => 'CSRF token mismatch.']);
    }
}
