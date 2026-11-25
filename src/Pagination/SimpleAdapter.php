<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Pagination;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Pharaonic\Laravel\ApiResponse\Contracts\PaginationAdapter;

/**
 * @internal
 */
final class SimpleAdapter implements PaginationAdapter
{
    public function supports(object $paginator): bool
    {
        return $paginator instanceof Paginator && ! $paginator instanceof LengthAwarePaginator;
    }

    public function items(object $paginator): mixed
    {
        return $paginator instanceof Paginator ? $paginator->items() : [];
    }

    public function meta(object $paginator, Closure $url): array
    {
        if (! $paginator instanceof Paginator) {
            return [];
        }

        $page = PageName::of($paginator, 'getPageName', 'page');

        return [
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
                'prev' => $url($paginator->previousPageUrl(), $page),
                'next' => $url($paginator->nextPageUrl(), $page),
            ],
        ];
    }
}
