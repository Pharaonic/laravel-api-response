<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Arr;

/**
 * The items and meta.pagination of Laravel's three paginators. Only what a
 * paginator knows is sent, and every link follows the response's query-string
 * policy without changing the paginator or the request.
 *
 * @internal
 */
final class Pagination
{
    /**
     * Cursor paginators exist from Laravel 8.41, so they are named by string and called dynamically.
     */
    private const CURSOR = 'Illuminate\Contracts\Pagination\CursorPaginator';

    /** @var list<string> */
    private array $sensitive;

    /**
     * @param  array<array-key, mixed>  $query  the request's query string
     * @param  list<string>|bool  $keep  true: every key, false: none, list: only these keys
     * @param  list<string>  $sensitive  keys never copied into a link
     */
    public function __construct(private array $query, private array|bool $keep, array $sensitive)
    {
        $this->sensitive = array_map('strtolower', $sensitive);
    }

    /**
     * @return array{items: mixed, meta: array<string, mixed>}|null null when the value is not a paginator
     */
    public function resolve(object $paginator): ?array
    {
        if ($paginator instanceof LengthAwarePaginator) {
            $page = $this->name($paginator, 'getPageName', 'page');

            return ['items' => $paginator->items(), 'meta' => [
                'type' => 'length_aware',
                'pages' => [
                    'current' => $paginator->currentPage(),
                    'last' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'has_more' => $paginator->hasMorePages(),
                ],
                'items' => [
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'links' => [
                    'first' => $this->url($paginator->url(1), $page),
                    'last' => $this->url($paginator->url(max(1, $paginator->lastPage())), $page),
                    'prev' => $this->url($paginator->previousPageUrl(), $page),
                    'next' => $this->url($paginator->nextPageUrl(), $page),
                ],
            ]];
        }

        if ($paginator instanceof Paginator) {
            $page = $this->name($paginator, 'getPageName', 'page');

            return ['items' => $paginator->items(), 'meta' => [
                'type' => 'simple',
                'pages' => [
                    'current' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'has_more' => $paginator->hasMorePages(),
                ],
                'items' => [
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'links' => [
                    'prev' => $this->url($paginator->previousPageUrl(), $page),
                    'next' => $this->url($paginator->nextPageUrl(), $page),
                ],
            ]];
        }

        if (is_a($paginator, self::CURSOR)) {
            $cursor = $this->name($paginator, 'getCursorName', 'cursor');
            $prev = $this->call($paginator, 'previousPageUrl');
            $next = $this->call($paginator, 'nextPageUrl');

            return ['items' => $this->call($paginator, 'items'), 'meta' => [
                'type' => 'cursor',
                'pages' => [
                    'per_page' => $this->call($paginator, 'perPage'),
                    'has_more' => (bool) $this->call($paginator, 'hasMorePages'),
                ],
                'cursors' => [
                    'current' => $this->encode($this->call($paginator, 'cursor')),
                    'prev' => $this->encode($this->call($paginator, 'previousCursor')),
                    'next' => $this->encode($this->call($paginator, 'nextCursor')),
                ],
                'links' => [
                    'prev' => $this->url(is_string($prev) ? $prev : null, $cursor),
                    'next' => $this->url(is_string($next) ? $next : null, $cursor),
                ],
            ]];
        }

        return null;
    }

    /**
     * Rebuild a paginator URL with the allowed query keys, then its page or cursor parameter.
     */
    private function url(?string $url, string $pageName): ?string
    {
        if ($url === null) {
            return null;
        }

        $fragment = '';

        if (($hash = strpos($url, '#')) !== false) {
            $fragment = substr($url, $hash);
            $url = substr($url, 0, $hash);
        }

        $parts = explode('?', $url, 2);
        parse_str($parts[1] ?? '', $own);

        $page = $own[$pageName] ?? null;
        unset($own[$pageName]);

        $query = array_filter(
            array_merge($this->keep === false ? [] : $this->query, $own),
            fn ($key) => $this->allows((string) $key, $pageName),
            ARRAY_FILTER_USE_KEY
        );

        if ($page !== null) {
            $query[$pageName] = $page;
        }

        return $parts[0].($query === [] ? '' : '?'.Arr::query($query)).$fragment;
    }

    private function allows(string $key, string $pageName): bool
    {
        if ($key === $pageName || $this->keep === false || in_array(strtolower($key), $this->sensitive, true)) {
            return false;
        }

        return $this->keep === true || in_array($key, $this->keep, true);
    }

    private function name(object $paginator, string $method, string $default): string
    {
        $name = $this->call($paginator, $method);

        return is_string($name) && $name !== '' ? $name : $default;
    }

    private function call(object $object, string $method): mixed
    {
        $callback = [$object, $method];

        return is_callable($callback) ? $callback() : null;
    }

    private function encode(mixed $cursor): ?string
    {
        $encoded = is_object($cursor) ? $this->call($cursor, 'encode') : null;

        return is_string($encoded) ? $encoded : null;
    }
}
