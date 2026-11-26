<?php

use Illuminate\Http\Request;
use Pharaonic\Laravel\ApiResponse\Response;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\Post;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\PostResource;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\ShareApiVersion;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('metadata shared in a middleware reaches the controller response', function () {
    $this->app['router']->get('api/posts/{id}', fn () => Response::success(['id' => 1]))->middleware(ShareApiVersion::class);

    $this->getJson('api/posts/1')->assertExactJson([
        'success' => true,
        'message' => null,
        'data' => ['id' => 1],
        'meta' => ['version' => '1.0', 'region' => 'eu'],
    ]);
});

test('a response meta replaces a shared key', function () {
    $this->app['router']->get('api/posts', fn () => Response::success()->meta('version', '2.0'))->middleware(ShareApiVersion::class);

    $this->getJson('api/posts')->assertJson(['meta' => ['version' => '2.0', 'region' => 'eu']]);
});

test('shared metadata comes before resource metadata and pagination', function () {
    $this->posts(3);
    $this->app['router']->get('api/posts', function () {
        Response::shareMeta(['resource' => 'shared', 'first' => true]);

        return Response::success(new PostResource(Post::query()->findOrFail(1)));
    });

    $meta = $this->getJson('api/posts')->json('meta');

    $this->assertSame(['resource' => 'post', 'first' => true, 'links' => ['self' => '/posts/1']], $meta);

    $this->app['router']->get('api/paged', function () {
        Response::shareMeta('first', true);

        return Response::success(Post::query()->paginate(2));
    });

    $this->assertSame(['first', 'pagination'], array_keys($this->getJson('api/paged')->json('meta')));
});

test('a rendered error carries the shared metadata', function () {
    $this->useApiExceptionHandler();
    $this->app['router']->get('api/fail', function (): void {
        throw new HttpException(409, 'Conflict.');
    })->middleware(ShareApiVersion::class);

    $this->getJson('api/fail')->assertStatus(409)->assertJson(['meta' => ['version' => '1.0', 'region' => 'eu']]);
});

test('shared metadata never reaches another request', function () {
    $this->app['router']->get('api/shared', fn () => Response::success())->middleware(ShareApiVersion::class);
    $this->app['router']->get('api/plain', fn () => Response::success());

    $this->getJson('api/shared')->assertJson(['meta' => ['version' => '1.0']]);
    $this->getJson('api/plain')->assertJson(['meta' => null]);
});

test('it is stored on the current request', function () {
    $first = Request::create('/api/one');
    $this->app->instance('request', $first);
    Response::shareMeta('only', 'first');

    $second = Request::create('/api/two');

    $this->assertSame(['only' => 'first'], $this->body(Response::success()->toResponse($first))['meta']);
    $this->assertNull($this->body(Response::success()->toResponse($second))['meta']);
});

test('the pagination key cannot be shared', function () {
    $this->app->instance('request', Request::create('/api/posts'));

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('[pagination]');

    Response::shareMeta('pagination', []);
});
