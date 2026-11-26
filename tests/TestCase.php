<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Pharaonic\Laravel\ApiResponse\ApiResponseServiceProvider;
use Pharaonic\Laravel\ApiResponse\Response;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\Post;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ApiResponseServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('app.url', 'http://localhost');
        $app['config']->set('app.debug', false);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function configure(array $config): void
    {
        foreach ($config as $key => $value) {
            $this->app['config']->set('pharaonic.api-response.'.$key, $value);
        }
    }

    /**
     * Build a response for a request to the given URI.
     */
    protected function build(Response $response, string $uri = '/api/posts'): SymfonyResponse
    {
        $request = Request::create($uri);
        $this->app->instance('request', $request);

        return $response->toResponse($request);
    }

    /**
     * Make the given URI the current request, as paginators read it when they are built.
     */
    protected function visit(string $uri): void
    {
        $this->app->instance('request', Request::create($uri));
    }

    /**
     * @return array<string, mixed>
     */
    protected function body(SymfonyResponse $response): array
    {
        $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($body);

        return $body;
    }

    /**
     * Create the posts table with the given number of rows.
     */
    protected function posts(int $count): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->string('secret')->nullable();
        });

        for ($i = 1; $i <= $count; $i++) {
            Post::query()->create(['title' => "Post {$i}", 'secret' => "secret {$i}"]);
        }
    }

    protected function requiresClass(string $class): void
    {
        if (! class_exists($class) && ! interface_exists($class)) {
            $this->markTestSkipped("{$class} is not available on this Laravel line.");
        }
    }
}
