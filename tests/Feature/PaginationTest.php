<?php

use Pharaonic\Laravel\ApiResponse\Response;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\Post;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\PostCollection;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\PostResource;

beforeEach(function () {
    $this->posts(5);
});

test('a length aware paginator sends items and full metadata', function () {
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
});

test('a simple paginator never invents a total', function () {
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
});

test('an empty page is sent as an empty list', function () {
    $this->visit('/api/posts?page=9');
    $body = $this->body($this->build(Response::success(Post::query()->paginate(2)), '/api/posts?page=9'));

    $this->assertSame([], $body['data']);
    $this->assertNull($body['meta']['pagination']['from']);
    $this->assertNull($body['meta']['pagination']['links']['next']);
    $this->assertFalse($body['meta']['pagination']['has_more_pages']);
});

test('a cursor paginator sends cursors only', function () {
    $this->requiresClass('Illuminate\Contracts\Pagination\CursorPaginator');

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
});

test('the query string is kept by default except sensitive keys', function () {
    $uri = '/api/posts?search=php&sort=title&token=secret&API_KEY=x';
    $this->visit($uri);
    $links = $this->body($this->build(Response::success(Post::query()->paginate(2)), $uri))['meta']['pagination']['links'];

    $this->assertSame('http://localhost/api/posts?search=php&sort=title&page=2', $links['next']);
});

test('with query string keeps only the named keys', function () {
    $uri = '/api/posts?search=php&sort=title&debug=1';
    $this->visit($uri);
    $links = $this->body($this->build(Response::success(Post::query()->paginate(2))->withQueryString(['search', 'sort']), $uri))['meta']['pagination']['links'];

    $this->assertSame('http://localhost/api/posts?search=php&sort=title&page=2', $links['next']);
});

test('without query string keeps only the page', function () {
    $uri = '/api/posts?search=php&page=1';
    $this->visit($uri);
    $paginator = Post::query()->paginate(2)->appends('filter', 'x');
    $links = $this->body($this->build(Response::success($paginator)->withoutQueryString(), $uri))['meta']['pagination']['links'];

    $this->assertSame('http://localhost/api/posts?page=2', $links['next']);
});

test('the default query string policy is configurable', function () {
    $this->configure(['pagination.query_string' => false]);
    $uri = '/api/posts?search=php';
    $this->visit($uri);

    $without = $this->body($this->build(Response::success(Post::query()->paginate(2)), $uri));
    $with = $this->body($this->build(Response::success(Post::query()->paginate(2))->withQueryString(), $uri));

    $this->assertSame('http://localhost/api/posts?page=2', $without['meta']['pagination']['links']['next']);
    $this->assertSame('http://localhost/api/posts?search=php&page=2', $with['meta']['pagination']['links']['next']);
});

test('a custom page name is kept', function () {
    $uri = '/api/posts?search=php';
    $this->visit($uri);
    $links = $this->body($this->build(Response::success(Post::query()->paginate(2, ['*'], 'p'))->withoutQueryString(), $uri))['meta']['pagination']['links'];

    $this->assertSame('http://localhost/api/posts?p=2', $links['next']);
});

test('the request is not changed', function () {
    $uri = '/api/posts?search=php&token=secret';
    $this->visit($uri);
    $request = $this->app->make('request');

    Response::success(Post::query()->paginate(2))->withoutQueryString()->toResponse($request);

    $this->assertSame(['search' => 'php', 'token' => 'secret'], $request->query->all());
});

test('a resource collection over a paginator is not wrapped twice', function () {
    $uri = '/api/posts?admin=1';
    $this->visit($uri);
    $body = $this->body($this->build(Response::success(PostResource::collection(Post::query()->paginate(2))), $uri));

    $this->assertSame([
        ['id' => 1, 'title' => 'Post 1', 'admin' => 'yes'],
        ['id' => 2, 'title' => 'Post 2', 'admin' => 'yes'],
    ], $body['data']);
    $this->assertSame('length_aware', $body['meta']['pagination']['type']);
    $this->assertSame(['pagination'], array_keys($body['meta']));
});

test('a resource collection class brings its metadata', function () {
    $this->visit('/api/posts');
    $body = $this->body($this->build(Response::success(new PostCollection(Post::query()->simplePaginate(2)))));

    $this->assertSame([['id' => 1, 'title' => 'Post 1'], ['id' => 2, 'title' => 'Post 2']], $body['data']);
    $this->assertSame(['collection', 'pagination'], array_keys($body['meta']));
    $this->assertTrue($body['meta']['collection']);
    $this->assertSame('simple', $body['meta']['pagination']['type']);
});

test('a single resource keeps its conditional values metadata and headers', function () {
    $response = $this->build(Response::success(new PostResource(Post::query()->findOrFail(1))));
    $body = $this->body($response);

    $this->assertSame(['id' => 1, 'title' => 'Post 1'], $body['data']);
    $this->assertSame(['links' => ['self' => '/posts/1'], 'resource' => 'post'], $body['meta']);
    $this->assertSame('post', $response->headers->get('X-Resource'));
});

test('additional resource data joins the metadata', function () {
    $resource = PostResource::collection(Post::query()->get())->additional(['meta' => ['count' => 5], 'version' => 2]);
    $body = $this->body($this->build(Response::success($resource)));

    $this->assertCount(5, $body['data']);
    $this->assertSame(['version' => 2, 'count' => 5], $body['meta']);
});

test('meta comes after resource metadata and before pagination', function () {
    $this->visit('/api/posts');
    $resource = new PostCollection(Post::query()->paginate(2));
    $body = $this->body($this->build(Response::success($resource)->meta(['collection' => 'replaced', 'version' => 1])));

    $this->assertSame(['collection', 'version', 'pagination'], array_keys($body['meta']));
    $this->assertSame('replaced', $body['meta']['collection']);
});
