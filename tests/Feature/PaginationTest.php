<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Feature;

use Pharaonic\Laravel\ApiResponse\Response;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\Post;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\PostCollection;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\PostResource;
use Pharaonic\Laravel\ApiResponse\Tests\TestCase;

final class PaginationTest extends TestCase
{
    private const CURSOR = 'Illuminate\Contracts\Pagination\CursorPaginator';

    protected function setUp(): void
    {
        parent::setUp();

        $this->posts(5);
    }

    public function test_a_length_aware_paginator_sends_items_and_full_metadata(): void
    {
        $this->visit('/api/posts?page=2');
        $body = $this->body($this->build(Response::success(Post::query()->paginate(2)), '/api/posts?page=2'));

        $this->assertSame([3, 4], array_column($body['data'], 'id'));
        $this->assertArrayNotHasKey('secret', $body['data'][0]);
        $this->assertSame([
            'type' => 'length_aware',
            'current_page' => 2,
            'last_page' => 3,
            'per_page' => 2,
            'total' => 5,
            'from' => 3,
            'to' => 4,
            'has_more_pages' => true,
            'links' => [
                'first' => 'http://localhost/api/posts?page=1',
                'last' => 'http://localhost/api/posts?page=3',
                'prev' => 'http://localhost/api/posts?page=1',
                'next' => 'http://localhost/api/posts?page=3',
            ],
        ], $body['meta']['pagination']);
    }

    public function test_a_simple_paginator_never_invents_a_total(): void
    {
        $this->visit('/api/posts');
        $body = $this->body($this->build(Response::success(Post::query()->simplePaginate(2))));

        $this->assertSame([1, 2], array_column($body['data'], 'id'));
        $this->assertSame([
            'type' => 'simple',
            'current_page' => 1,
            'per_page' => 2,
            'from' => 1,
            'to' => 2,
            'has_more_pages' => true,
            'links' => [
                'prev' => null,
                'next' => 'http://localhost/api/posts?page=2',
            ],
        ], $body['meta']['pagination']);
    }

    public function test_an_empty_page_is_sent_as_an_empty_list(): void
    {
        $this->visit('/api/posts?page=9');
        $body = $this->body($this->build(Response::success(Post::query()->paginate(2)), '/api/posts?page=9'));

        $this->assertSame([], $body['data']);
        $this->assertNull($body['meta']['pagination']['from']);
        $this->assertNull($body['meta']['pagination']['links']['next']);
        $this->assertFalse($body['meta']['pagination']['has_more_pages']);
    }

    public function test_a_cursor_paginator_sends_cursors_only(): void
    {
        $this->requiresClass(self::CURSOR);

        $this->visit('/api/posts');
        $first = $this->body($this->build(Response::success(Post::query()->orderBy('id')->cursorPaginate(2))));
        $pagination = $first['meta']['pagination'];

        $this->assertSame([1, 2], array_column($first['data'], 'id'));
        $this->assertSame(['type', 'per_page', 'next_cursor', 'prev_cursor', 'has_more_pages', 'links'], array_keys($pagination));
        $this->assertNull($pagination['prev_cursor']);
        $this->assertIsString($pagination['next_cursor']);

        $this->visit('/api/posts?cursor='.$pagination['next_cursor']);
        $second = $this->body($this->build(Response::success(Post::query()->orderBy('id')->cursorPaginate(2)), '/api/posts?cursor='.$pagination['next_cursor']));

        $this->assertSame([3, 4], array_column($second['data'], 'id'));
        $this->assertIsString($second['meta']['pagination']['prev_cursor']);
    }

    public function test_the_query_string_is_kept_by_default_except_sensitive_keys(): void
    {
        $uri = '/api/posts?search=php&sort=title&token=secret&API_KEY=x';
        $this->visit($uri);
        $links = $this->body($this->build(Response::success(Post::query()->paginate(2)), $uri))['meta']['pagination']['links'];

        $this->assertSame('http://localhost/api/posts?search=php&sort=title&page=2', $links['next']);
    }

    public function test_with_query_string_keeps_only_the_named_keys(): void
    {
        $uri = '/api/posts?search=php&sort=title&debug=1';
        $this->visit($uri);
        $links = $this->body($this->build(Response::success(Post::query()->paginate(2))->withQueryString(['search', 'sort']), $uri))['meta']['pagination']['links'];

        $this->assertSame('http://localhost/api/posts?search=php&sort=title&page=2', $links['next']);
    }

    public function test_without_query_string_keeps_only_the_page(): void
    {
        $uri = '/api/posts?search=php&page=1';
        $this->visit($uri);
        $paginator = Post::query()->paginate(2)->appends('filter', 'x');
        $links = $this->body($this->build(Response::success($paginator)->withoutQueryString(), $uri))['meta']['pagination']['links'];

        $this->assertSame('http://localhost/api/posts?page=2', $links['next']);
    }

    public function test_the_default_query_string_policy_is_configurable(): void
    {
        $this->configure(['pagination.query_string' => false]);
        $uri = '/api/posts?search=php';
        $this->visit($uri);

        $without = $this->body($this->build(Response::success(Post::query()->paginate(2)), $uri));
        $with = $this->body($this->build(Response::success(Post::query()->paginate(2))->withQueryString(), $uri));

        $this->assertSame('http://localhost/api/posts?page=2', $without['meta']['pagination']['links']['next']);
        $this->assertSame('http://localhost/api/posts?search=php&page=2', $with['meta']['pagination']['links']['next']);
    }

    public function test_a_custom_page_name_is_kept(): void
    {
        $uri = '/api/posts?search=php';
        $this->visit($uri);
        $links = $this->body($this->build(Response::success(Post::query()->paginate(2, ['*'], 'p'))->withoutQueryString(), $uri))['meta']['pagination']['links'];

        $this->assertSame('http://localhost/api/posts?p=2', $links['next']);
    }

    public function test_the_request_is_not_changed(): void
    {
        $uri = '/api/posts?search=php&token=secret';
        $this->visit($uri);
        $request = $this->app->make('request');

        Response::success(Post::query()->paginate(2))->withoutQueryString()->toResponse($request);

        $this->assertSame(['search' => 'php', 'token' => 'secret'], $request->query->all());
    }

    public function test_a_resource_collection_over_a_paginator_is_not_wrapped_twice(): void
    {
        $uri = '/api/posts?admin=1';
        $this->visit($uri);
        $body = $this->body($this->build(Response::success(PostResource::collection(Post::query()->paginate(2))), $uri));

        $this->assertSame([
            ['id' => 1, 'title' => 'Post 1', 'admin' => 'yes'],
            ['id' => 2, 'title' => 'Post 2', 'admin' => 'yes'],
        ], $body['data']);
        $this->assertSame('length_aware', $body['meta']['pagination']['type']);
        $this->assertSame(['pagination'], array_keys($body['meta']));
    }

    public function test_a_resource_collection_class_brings_its_metadata(): void
    {
        $this->visit('/api/posts');
        $body = $this->body($this->build(Response::success(new PostCollection(Post::query()->simplePaginate(2)))));

        $this->assertSame([['id' => 1, 'title' => 'Post 1'], ['id' => 2, 'title' => 'Post 2']], $body['data']);
        $this->assertSame(['collection', 'pagination'], array_keys($body['meta']));
        $this->assertTrue($body['meta']['collection']);
        $this->assertSame('simple', $body['meta']['pagination']['type']);
    }

    public function test_a_single_resource_keeps_its_conditional_values_metadata_and_headers(): void
    {
        $response = $this->build(Response::success(new PostResource(Post::query()->findOrFail(1))));
        $body = $this->body($response);

        $this->assertSame(['id' => 1, 'title' => 'Post 1'], $body['data']);
        $this->assertSame(['links' => ['self' => '/posts/1'], 'resource' => 'post'], $body['meta']);
        $this->assertSame('post', $response->headers->get('X-Resource'));
    }

    public function test_additional_resource_data_joins_the_metadata(): void
    {
        $resource = PostResource::collection(Post::query()->get())->additional(['meta' => ['count' => 5], 'version' => 2]);
        $body = $this->body($this->build(Response::success($resource)));

        $this->assertCount(5, $body['data']);
        $this->assertSame(['version' => 2, 'count' => 5], $body['meta']);
    }

    public function test_meta_comes_after_resource_metadata_and_before_pagination(): void
    {
        $this->visit('/api/posts');
        $resource = new PostCollection(Post::query()->paginate(2));
        $body = $this->body($this->build(Response::success($resource)->meta(['collection' => 'replaced', 'version' => 1])));

        $this->assertSame(['collection', 'version', 'pagination'], array_keys($body['meta']));
        $this->assertSame('replaced', $body['meta']['collection']);
    }
}
