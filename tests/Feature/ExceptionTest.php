<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\TokenMismatchException;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\ConfiguredProfile;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\OwnRenderException;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\Post;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->useApiExceptionHandler();
});

test('a validation exception sends 422 with its errors', function () {
    $this->app['router']->get('api/validate', fn (Request $request) => $request->validate(['email' => 'required|email']));

    $this->getJson('api/validate?email=nope')
        ->assertStatus(422)
        ->assertExactJson([
            'success' => false,
            'message' => 'The email field must be a valid email address.',
            'errors' => ['email' => ['The email field must be a valid email address.']],
            'meta' => null,
        ]);
});

test('laravel exceptions get their status', function () {
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
});

test('a missing route sends 404', function () {
    $this->getJson('api/missing')->assertStatus(404)->assertJson(['success' => false, 'errors' => null, 'meta' => null]);
});

test('a model not found never names the model', function () {
    $this->posts(0);
    $this->app['router']->get('api/model', fn () => Post::query()->findOrFail(99));

    $content = (string) $this->getJson('api/model')->getContent();

    $this->assertStringNotContainsString('Post', $content);
    $this->assertStringNotContainsString('99', $content);
});

test('a wrong method keeps the allow header', function () {
    $this->app['router']->get('api/posts', fn () => 'ok');

    $response = $this->postJson('api/posts');

    $response->assertStatus(405)->assertJson(['success' => false]);
    $this->assertSame('GET, HEAD', $response->headers->get('Allow'));
});

test('throttling sends 429 with its headers', function () {
    $this->app['router']->get('api/limited', fn () => 'ok')->middleware(ThrottleRequests::class.':1,1');

    $this->getJson('api/limited')->assertOk();

    $response = $this->getJson('api/limited');

    $response->assertStatus(429)->assertExactJson(['success' => false, 'message' => 'Too Many Attempts.', 'errors' => null, 'meta' => null]);
    $this->assertNotNull($response->headers->get('Retry-After'));
    $this->assertSame('0', $response->headers->get('X-RateLimit-Remaining'));
});

test('an http exception keeps its headers', function () {
    $this->throwing('api/challenge', new HttpException(401, 'Token expired.', null, ['WWW-Authenticate' => 'Bearer']));

    $response = $this->getJson('api/challenge');

    $response->assertStatus(401)->assertJson(['message' => 'Token expired.']);
    $this->assertSame('Bearer', $response->headers->get('WWW-Authenticate'));
});

test('an unexpected exception sends a bare 500 in production', function () {
    $this->throwing('api/crash', new RuntimeException('SQLSTATE secret at /var/www/app.php'));

    $response = $this->getJson('api/crash');
    $content = (string) $response->getContent();

    $response->assertStatus(500)->assertExactJson(['success' => false, 'message' => 'Server Error.', 'errors' => null, 'meta' => null]);
    $this->assertStringNotContainsString('SQLSTATE', $content);
    $this->assertStringNotContainsString('/var/www', $content);
    $this->assertStringNotContainsString('trace', $content);
});

test('an unexpected exception is left to laravel in debug mode', function () {
    $this->app['config']->set('app.debug', true);
    $this->throwing('api/crash', new RuntimeException('Visible while debugging.'));

    $response = $this->getJson('api/crash');

    $response->assertStatus(500)->assertJsonMissing(['success' => false]);
    $this->assertStringContainsString('Visible while debugging.', (string) $response->getContent());
});

test('exceptions are still reported once', function () {
    $this->throwing('api/crash', new RuntimeException('Boom.'));

    $this->getJson('api/crash')->assertStatus(500);

    $this->assertSame([RuntimeException::class], $this->handler->reported);
});

test('a path in exceptions paths is rendered without accept json', function () {
    $this->throwing('api/conflict', new HttpException(409, 'Already exists.'));

    $this->get('api/conflict')->assertStatus(409)->assertExactJson(['success' => false, 'message' => 'Already exists.', 'errors' => null, 'meta' => null]);
});

test('a web page keeps laravel rendering', function () {
    $this->throwing('page', new HttpException(409, 'Already exists.'));

    $response = $this->get('page');

    $response->assertStatus(409);
    $this->assertStringNotContainsString('"success"', (string) $response->getContent());
});

test('a web page that expects json is rendered', function () {
    $this->throwing('page', new HttpException(409, 'Already exists.'));

    $this->getJson('page')->assertStatus(409)->assertJson(['success' => false]);
});

test('without paths only json requests are rendered', function () {
    $this->configure(['exceptions.paths' => []]);
    $this->throwing('api/conflict', new HttpException(409, 'Already exists.'));

    $this->assertStringNotContainsString('"success"', (string) $this->get('api/conflict')->getContent());
    $this->getJson('api/conflict')->assertJson(['success' => false]);
});

test('exceptions with their own response are left to laravel', function () {
    $this->throwing('api/own', new OwnRenderException);
    $this->throwing('api/http-response', new HttpResponseException(new JsonResponse(['raw' => true], 418)));

    $this->getJson('api/own')->assertStatus(409)->assertExactJson(['own' => true]);
    $this->getJson('api/http-response')->assertStatus(418)->assertExactJson(['raw' => true]);
});

test('errors follow the default profile', function () {
    $this->configure([
        'profiles.configured' => ConfiguredProfile::class,
        'default_profile' => 'configured',
    ]);
    $this->app['config']->set('testing.profile_fields', ['message' => 'msg', 'errors' => 'problems']);
    $this->throwing('api/conflict', new HttpException(409, 'Already exists.'));

    $this->getJson('api/conflict')->assertExactJson(['success' => false, 'msg' => 'Already exists.', 'problems' => null, 'meta' => null]);
});

test('only an http exception is read through its previous exception', function () {
    $this->throwing('api/wrapped', new RuntimeException('Failed.', 0, new ModelNotFoundException));

    $this->getJson('api/wrapped')->assertStatus(500)->assertJson(['message' => 'Server Error.']);
});
