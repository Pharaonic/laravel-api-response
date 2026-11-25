<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Fixtures;

use Closure;
use Pharaonic\Laravel\ApiResponse\Contracts\PaginationAdapter;

class ExternalPageAdapter implements PaginationAdapter
{
    public function supports(object $paginator): bool
    {
        return $paginator instanceof ExternalPage;
    }

    public function items(object $paginator): mixed
    {
        return $paginator instanceof ExternalPage ? $paginator->items : [];
    }

    public function meta(object $paginator, Closure $url): array
    {
        if (! $paginator instanceof ExternalPage) {
            return [];
        }

        return [
            'type' => 'token',
            'pages' => ['has_more' => $paginator->nextToken !== null],
            'links' => [
                'next' => $paginator->nextToken === null ? null : $url($paginator->path.'?token_page='.$paginator->nextToken, 'token_page'),
            ],
        ];
    }
}
