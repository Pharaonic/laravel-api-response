<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Pharaonic\Laravel\ApiResponse\Response;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\ExternalPage;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\ExternalPageAdapter;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\Post;
use Pharaonic\Laravel\ApiResponse\Tests\TestCase;

final class PaginationTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function paginate(string $uri, callable $paginator, ?callable $configure = null): array
    {
        $this->visit($uri);
        $response = Response::success($paginator());

        if ($configure) {
            $configure($response);
        }

        return $this->body($this->finalize($response, $uri));
    }

    public function test_a_length_aware_paginator_sends_its_items_and_metadata(): void
    {
        $this->posts(5);

        $body = $this->paginate('/api/posts', fn () => Post::query()->paginate(2));

        $this->assertSame([1, 2], array_column($body['data'], 'id'));
        $this->assertSame([
            'type' => 'length_aware',
            'pages' => ['current' => 1, 'last' => 3, 'per_page' => 2, 'has_more' => true],
            'items' => ['total' => 5, 'from' => 1, 'to' => 2],
            'links' => [
                'first' => 'http://localhost/api/posts?page=1',
                'last' => 'http://localhost/api/posts?page=3',
                'prev' => null,
                'next' => 'http://localhost/api/posts?page=2',
            ],
        ], $body['meta']['pagination']);
    }

    public function test_the_middle_and_last_pages_of_a_length_aware_paginator(): void
    {
        $this->posts(5);

        $middle = $this->paginate('/api/posts?page=2', fn () => Post::query()->paginate(2))['meta']['pagination'];
        $this->assertSame(2, $middle['pages']['current']);
        $this->assertSame('http://localhost/api/posts?page=1', $middle['links']['prev']);
        $this->assertSame('http://localhost/api/posts?page=3', $middle['links']['next']);

        $last = $this->paginate('/api/posts?page=3', fn () => Post::query()->paginate(2));
        $this->assertSame([5], array_column($last['data'], 'id'));
        $this->assertFalse($last['meta']['pagination']['pages']['has_more']);
        $this->assertNull($last['meta']['pagination']['links']['next']);
        $this->assertSame(['total' => 5, 'from' => 5, 'to' => 5], $last['meta']['pagination']['items']);
    }

    public function test_an_empty_length_aware_page(): void
    {
        $this->posts(0);

        $body = $this->paginate('/api/posts', fn () => Post::query()->paginate(2));

        $this->assertSame([], $body['data']);
        $this->assertSame(['total' => 0, 'from' => null, 'to' => null], $body['meta']['pagination']['items']);
        $this->assertSame(1, $body['meta']['pagination']['pages']['last']);
        $this->assertSame('http://localhost/api/posts?page=1', $body['meta']['pagination']['links']['last']);
    }

    public function test_a_simple_paginator_never_invents_a_total(): void
    {
        $this->posts(3);

        $body = $this->paginate('/api/posts?page=2', fn () => Post::query()->simplePaginate(2));

        $this->assertSame([3], array_column($body['data'], 'id'));
        $this->assertSame([
            'type' => 'simple',
            'pages' => ['current' => 2, 'per_page' => 2, 'has_more' => false],
            'items' => ['from' => 3, 'to' => 3],
            'links' => [
                'prev' => 'http://localhost/api/posts?page=1',
                'next' => null,
            ],
        ], $body['meta']['pagination']);
    }

    public function test_a_cursor_paginator_sends_only_cursor_metadata(): void
    {
        $this->requiresClass('Illuminate\Contracts\Pagination\CursorPaginator');
        $this->posts(5);

        $first = $this->paginate('/api/posts?search=a', fn () => Post::query()->orderBy('id')->cursorPaginate(2));
        $pagination = $first['meta']['pagination'];

        $this->assertSame([1, 2], array_column($first['data'], 'id'));
        $this->assertSame(['type', 'pages', 'cursors', 'links'], array_keys($pagination));
        $this->assertSame('cursor', $pagination['type']);
        $this->assertSame(['per_page' => 2, 'has_more' => true], $pagination['pages']);
        $this->assertArrayNotHasKey('items', $pagination);
        $this->assertNull($pagination['cursors']['current']);
        $this->assertNull($pagination['cursors']['prev']);
        $this->assertIsString($pagination['cursors']['next']);
        $this->assertSame('http://localhost/api/posts?search=a&cursor='.$pagination['cursors']['next'], $pagination['links']['next']);

        $second = $this->paginate('/api/posts?cursor='.$pagination['cursors']['next'], fn () => Post::query()->orderBy('id')->cursorPaginate(2));
        $this->assertSame([3, 4], array_column($second['data'], 'id'));
        $this->assertSame($pagination['cursors']['next'], $second['meta']['pagination']['cursors']['current']);
        $this->assertIsString($second['meta']['pagination']['cursors']['prev']);

        $back = $this->paginate('/api/posts?cursor='.$second['meta']['pagination']['cursors']['prev'], fn () => Post::query()->orderBy('id')->cursorPaginate(2));
        $this->assertSame([1, 2], array_column($back['data'], 'id'));
    }

    public function test_the_query_string_is_kept_by_default_except_denied_keys(): void
    {
        $this->posts(5);

        $links = $this->paginate('/api/posts?search=caf%C3%A9&sort=-id&TOKEN=secret&api_key=k&page=2', fn () => Post::query()->paginate(2))['meta']['pagination']['links'];

        $this->assertSame('http://localhost/api/posts?search=caf%C3%A9&sort=-id&page=3', $links['next']);
        $this->assertStringNotContainsString('secret', (string) json_encode($links));
    }

    public function test_with_query_string_keeps_only_the_allowed_keys(): void
    {
        $this->posts(5);

        $links = $this->paginate(
            '/api/posts?search=a&sort=b&filter[status]=draft&token=x',
            fn () => Post::query()->paginate(2),
            fn (Response $r) => $r->withQueryString(['search', 'filter', 'token'])
        )['meta']['pagination']['links'];

        $this->assertSame('http://localhost/api/posts?search=a&filter%5Bstatus%5D=draft&page=2', $links['next']);
    }

    public function test_without_query_string_keeps_only_the_page(): void
    {
        $this->posts(5);

        $links = $this->paginate(
            '/api/posts?search=a&page=2',
            fn () => Post::query()->paginate(2)->appends('extra', '1'),
            fn (Response $r) => $r->withoutQueryString()
        )['meta']['pagination']['links'];

        $this->assertSame('http://localhost/api/posts?page=3', $links['next']);
        $this->assertSame('http://localhost/api/posts?page=1', $links['prev']);
    }

    public function test_the_configured_default_can_drop_the_query_string(): void
    {
        $this->posts(5);
        $this->configure(['pagination.query_string.enabled' => false]);

        $this->assertSame(
            'http://localhost/api/posts?page=2',
            $this->paginate('/api/posts?search=a', fn () => Post::query()->paginate(2))['meta']['pagination']['links']['next']
        );

        $this->assertSame(
            'http://localhost/api/posts?search=a&page=2',
            $this->paginate('/api/posts?search=a', fn () => Post::query()->paginate(2), fn (Response $r) => $r->withQueryString())['meta']['pagination']['links']['next']
        );
    }

    public function test_a_custom_page_name_is_kept(): void
    {
        $this->posts(5);

        $body = $this->paginate('/api/posts?p=2&search=a', fn () => Post::query()->paginate(2, ['*'], 'p'), fn (Response $r) => $r->withoutQueryString());

        $this->assertSame(2, $body['meta']['pagination']['pages']['current']);
        $this->assertSame('http://localhost/api/posts?p=3', $body['meta']['pagination']['links']['next']);
    }

    public function test_the_paginator_and_request_are_not_changed(): void
    {
        $this->posts(5);
        $this->visit('/api/posts?search=a');
        $paginator = Post::query()->paginate(2);

        $this->finalize(Response::success($paginator)->withQueryString(['search']), '/api/posts?search=a');

        $this->assertSame('http://localhost/api/posts?page=2', $paginator->nextPageUrl());
    }

    public function test_manually_built_paginators_are_recognised(): void
    {
        $this->visit('/api/items');

        $body = $this->body($this->finalize(Response::success(new LengthAwarePaginator(['a', 'b'], 10, 2, 1, ['path' => '/api/items'])), '/api/items'));
        $this->assertSame(['a', 'b'], $body['data']);
        $this->assertSame(5, $body['meta']['pagination']['pages']['last']);

        $simple = $this->body($this->finalize(Response::success(new Paginator(['a', 'b', 'c'], 2, 1, ['path' => '/api/items'])), '/api/items'));
        $this->assertSame(['a', 'b'], $simple['data']);
        $this->assertTrue($simple['meta']['pagination']['pages']['has_more']);
    }

    public function test_a_registered_adapter_paginates_other_objects(): void
    {
        $this->configure(['pagination.adapters' => [ExternalPageAdapter::class]]);

        $body = $this->body($this->finalize(
            Response::success(new ExternalPage([['id' => 1]], 'abc', '/api/remote')),
            '/api/remote?search=x&token=t'
        ));

        $this->assertSame([['id' => 1]], $body['data']);
        $this->assertSame([
            'type' => 'token',
            'pages' => ['has_more' => true],
            'links' => ['next' => '/api/remote?search=x&token_page=abc'],
        ], $body['meta']['pagination']);
    }

    public function test_pagination_can_be_turned_off_without_losing_the_items(): void
    {
        $this->posts(3);

        $body = $this->paginate('/api/posts', fn () => Post::query()->paginate(2), fn (Response $r) => $r->withoutPagination());
        $this->assertSame([1, 2], array_column($body['data'], 'id'));
        $this->assertArrayNotHasKey('meta', $body);

        $this->configure(['pagination.enabled' => false]);
        $this->assertArrayNotHasKey('meta', $this->paginate('/api/posts', fn () => Post::query()->paginate(2)));
    }

    public function test_pagination_merges_with_caller_metadata(): void
    {
        $this->posts(3);

        $body = $this->paginate('/api/posts', fn () => Post::query()->paginate(2), fn (Response $r) => $r->meta('version', '1.0'));

        $this->assertSame(['version', 'pagination'], array_keys($body['meta']));
    }
}
