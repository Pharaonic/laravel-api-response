<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Contracts;

use Closure;

/**
 * Turns a paginator the package does not know into items and pagination
 * metadata. Register it under pagination.adapters; registered adapters are
 * asked before the built-in ones.
 */
interface PaginationAdapter
{
    public function supports(object $paginator): bool;

    /**
     * The items of the current page.
     */
    public function items(object $paginator): mixed;

    /**
     * The pagination metadata. Pass every URL you send through $url, which
     * applies the response's query-string policy and keeps $pageName.
     * Send only what the paginator really knows, grouped like the built-in
     * adapters: type, pages, items (or cursors), links.
     *
     * @param  Closure(?string, string): ?string  $url  receives a URL and its page or cursor parameter name
     * @return array<string, mixed>
     */
    public function meta(object $paginator, Closure $url): array;
}
