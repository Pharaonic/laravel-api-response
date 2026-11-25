<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Pagination;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Pharaonic\Laravel\ApiResponse\Contracts\PaginationAdapter;

/**
 * @internal
 */
final class LengthAwareAdapter implements PaginationAdapter
{
    public function supports(object $paginator): bool
    {
        return $paginator instanceof LengthAwarePaginator;
    }

    public function items(object $paginator): mixed
    {
        return $paginator instanceof LengthAwarePaginator ? $paginator->items() : [];
    }

    public function meta(object $paginator, Closure $url): array
    {
        if (! $paginator instanceof LengthAwarePaginator) {
            return [];
        }

        $page = PageName::of($paginator, 'getPageName', 'page');

        return [
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
                'first' => $url($paginator->url(1), $page),
                'last' => $url($paginator->url(max(1, $paginator->lastPage())), $page),
                'prev' => $url($paginator->previousPageUrl(), $page),
                'next' => $url($paginator->nextPageUrl(), $page),
            ],
        ];
    }
}
