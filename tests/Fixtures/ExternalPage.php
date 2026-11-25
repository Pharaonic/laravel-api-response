<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Fixtures;

/**
 * A page of results from an external API, paginated with an opaque token.
 */
class ExternalPage
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function __construct(public array $items, public ?string $nextToken, public string $path)
    {
    }
}
