<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Feature;

use Pharaonic\Laravel\ApiResponse\Response;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\Post;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\PostCollection;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\PostResource;
use Pharaonic\Laravel\ApiResponse\Tests\TestCase;

final class ResourceTest extends TestCase
{
    public function test_a_resource_is_resolved_without_a_data_wrapper(): void
    {
        $this->posts(1);

        $response = $this->finalize(Response::success(new PostResource(Post::query()->first())));
        $body = $this->body($response);

        $this->assertSame(['id' => 1, 'title' => 'Post 1'], $body['data']);
        $this->assertSame(['links' => ['self' => '/posts/1'], 'resource' => 'post'], $body['meta']);
        $this->assertSame('post', $response->headers->get('X-Resource'));
    }

    public function test_conditional_fields_see_the_current_request(): void
    {
        $this->posts(1);

        $body = $this->body($this->finalize(Response::success(new PostResource(Post::query()->first())), '/api/posts?admin=1'));

        $this->assertSame('yes', $body['data']['admin']);
    }

    public function test_additional_values_and_caller_meta_are_merged_with_the_caller_winning(): void
    {
        $this->posts(1);
        $resource = (new PostResource(Post::query()->first()))->additional(['meta' => ['resource' => 'additional', 'extra' => 1], 'top' => 'kept']);

        $body = $this->body($this->finalize(Response::success($resource)->meta('resource', 'caller')));

        $this->assertSame('caller', $body['meta']['resource']);
        $this->assertSame(1, $body['meta']['extra']);
        $this->assertSame('kept', $body['meta']['top']);
        $this->assertSame(['self' => '/posts/1'], $body['meta']['links']);
    }

    public function test_meta_from_with_and_additional_is_merged_key_by_key(): void
    {
        $this->posts(1);
        $resource = (new PostResource(Post::query()->first()))->additional(['meta' => ['extra' => 1]]);

        $body = $this->body($this->finalize(Response::success($resource)));

        $this->assertSame('post', $body['meta']['resource']);
        $this->assertSame(1, $body['meta']['extra']);
    }

    public function test_an_anonymous_collection_of_an_unpaginated_query(): void
    {
        $this->posts(2);

        $body = $this->body($this->finalize(Response::success(PostResource::collection(Post::query()->get()))));

        $this->assertSame([['id' => 1, 'title' => 'Post 1'], ['id' => 2, 'title' => 'Post 2']], $body['data']);
        $this->assertArrayNotHasKey('pagination', $body['meta'] ?? []);
    }

    public function test_an_empty_resource_collection_stays_an_empty_list(): void
    {
        $this->posts(0);

        $body = $this->body($this->finalize(Response::success(PostResource::collection(Post::query()->get()))));

        $this->assertSame([], $body['data']);
    }

    public function test_a_paginated_resource_collection_sends_transformed_items_and_normalized_pagination(): void
    {
        $this->posts(3);
        $this->visit('/api/posts?search=x');

        $body = $this->body($this->finalize(Response::success(PostResource::collection(Post::query()->paginate(2))), '/api/posts?search=x'));

        $this->assertSame([['id' => 1, 'title' => 'Post 1'], ['id' => 2, 'title' => 'Post 2']], $body['data']);
        $this->assertSame('length_aware', $body['meta']['pagination']['type']);
        $this->assertSame(3, $body['meta']['pagination']['items']['total']);
        $this->assertSame('http://localhost/api/posts?search=x&page=2', $body['meta']['pagination']['links']['next']);
        $this->assertArrayNotHasKey('data', $body['data']);
    }

    public function test_a_custom_collection_keeps_its_metadata_and_pagination_information(): void
    {
        $this->posts(3);
        $this->visit('/api/posts');

        $body = $this->body($this->finalize(Response::success(new PostCollection(Post::query()->simplePaginate(2)))));

        $this->assertSame([1, 2], array_column($body['data'], 'id'));
        $this->assertTrue($body['meta']['collection']);
        $this->assertSame('collection', $body['meta']['source']);
        $this->assertSame('simple', $body['meta']['pagination']['type']);
        $this->assertTrue($body['meta']['pagination']['custom']);
    }

    public function test_a_paginated_resource_without_pagination_keeps_its_items(): void
    {
        $this->posts(3);
        $this->visit('/api/posts');

        $body = $this->body($this->finalize(Response::success(PostResource::collection(Post::query()->paginate(2)))->withoutPagination()));

        $this->assertSame([1, 2], array_column($body['data'], 'id'));
        $this->assertArrayNotHasKey('meta', $body);
    }

    public function test_a_cursor_paginated_resource_collection(): void
    {
        $this->requiresClass('Illuminate\Contracts\Pagination\CursorPaginator');
        $this->posts(3);
        $this->visit('/api/posts');

        $body = $this->body($this->finalize(Response::success(PostResource::collection(Post::query()->orderBy('id')->cursorPaginate(2)))));

        $this->assertSame([1, 2], array_column($body['data'], 'id'));
        $this->assertSame('cursor', $body['meta']['pagination']['type']);
    }

    public function test_the_builder_status_wins_over_a_recently_created_model(): void
    {
        $this->posts(0);
        $post = Post::query()->create(['title' => 'New']);

        $this->assertSame(200, $this->finalize(Response::success(new PostResource($post)))->getStatusCode());
        $this->assertSame(201, $this->finalize(Response::created(new PostResource($post)))->getStatusCode());
    }

    public function test_resources_inside_an_array_are_resolved(): void
    {
        $this->posts(1);

        $body = $this->body($this->finalize(Response::success(['post' => new PostResource(Post::query()->first())])));

        $this->assertSame(['id' => 1, 'title' => 'Post 1'], $body['data']['post']);
    }
}
