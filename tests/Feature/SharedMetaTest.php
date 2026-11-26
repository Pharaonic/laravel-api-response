<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Pharaonic\Laravel\ApiResponse\Response;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\ApiExceptionHandler;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\Post;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\PostResource;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\ShareApiVersion;
use Pharaonic\Laravel\ApiResponse\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class SharedMetaTest extends TestCase
{
    public function test_metadata_shared_in_a_middleware_reaches_the_controller_response(): void
    {
        $this->app['router']->get('api/posts/{id}', fn () => Response::success(['id' => 1]))->middleware(ShareApiVersion::class);

        $this->getJson('api/posts/1')->assertExactJson([
            'success' => true,
            'message' => null,
            'data' => ['id' => 1],
            'meta' => ['version' => '1.0', 'region' => 'eu'],
        ]);
    }

    public function test_a_response_meta_replaces_a_shared_key(): void
    {
        $this->app['router']->get('api/posts', fn () => Response::success()->meta('version', '2.0'))->middleware(ShareApiVersion::class);

        $this->getJson('api/posts')->assertJson(['meta' => ['version' => '2.0', 'region' => 'eu']]);
    }

    public function test_shared_metadata_comes_before_resource_metadata_and_pagination(): void
    {
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
    }

    public function test_a_rendered_error_carries_the_shared_metadata(): void
    {
        $this->app->instance(ExceptionHandler::class, new ApiExceptionHandler($this->app));
        $this->app['router']->get('api/fail', function (): void {
            throw new HttpException(409, 'Conflict.');
        })->middleware(ShareApiVersion::class);

        $this->getJson('api/fail')->assertStatus(409)->assertJson(['meta' => ['version' => '1.0', 'region' => 'eu']]);
    }

    public function test_shared_metadata_never_reaches_another_request(): void
    {
        $this->app['router']->get('api/shared', fn () => Response::success())->middleware(ShareApiVersion::class);
        $this->app['router']->get('api/plain', fn () => Response::success());

        $this->getJson('api/shared')->assertJson(['meta' => ['version' => '1.0']]);
        $this->getJson('api/plain')->assertJson(['meta' => null]);
    }

    public function test_it_is_stored_on_the_current_request(): void
    {
        $first = Request::create('/api/one');
        $this->app->instance('request', $first);
        Response::shareMeta('only', 'first');

        $second = Request::create('/api/two');

        $this->assertSame(['only' => 'first'], $this->body(Response::success()->toResponse($first))['meta']);
        $this->assertNull($this->body(Response::success()->toResponse($second))['meta']);
    }

    public function test_the_pagination_key_cannot_be_shared(): void
    {
        $this->app->instance('request', Request::create('/api/posts'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[pagination]');

        Response::shareMeta('pagination', []);
    }
}
