<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Pagination;

use Closure;
use Pharaonic\Laravel\ApiResponse\Contracts\PaginationAdapter;

/**
 * Cursor paginators exist from Laravel 8.41. They are named by string so the
 * same code runs on every line.
 *
 * @internal
 */
final class CursorAdapter implements PaginationAdapter
{
    private const CONTRACT = 'Illuminate\Contracts\Pagination\CursorPaginator';

    public function supports(object $paginator): bool
    {
        return is_a($paginator, self::CONTRACT);
    }

    public function items(object $paginator): mixed
    {
        return $this->call($paginator, 'items');
    }

    public function meta(object $paginator, Closure $url): array
    {
        $name = PageName::of($paginator, 'getCursorName', 'cursor');
        $prev = $this->call($paginator, 'previousPageUrl');
        $next = $this->call($paginator, 'nextPageUrl');

        return [
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
                'prev' => $url(is_string($prev) ? $prev : null, $name),
                'next' => $url(is_string($next) ? $next : null, $name),
            ],
        ];
    }

    private function call(object $paginator, string $method): mixed
    {
        $callback = [$paginator, $method];

        return is_callable($callback) ? $callback() : null;
    }

    private function encode(mixed $cursor): ?string
    {
        if (! is_object($cursor)) {
            return null;
        }

        $encoded = $this->call($cursor, 'encode');

        return is_string($encoded) ? $encoded : null;
    }
}
