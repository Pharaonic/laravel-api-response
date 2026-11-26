<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Fixtures;

/**
 * Stands in for the manager of pharaonic/laravel-seo: only toArray() is used.
 */
class FakeSeo
{
    public int $calls = 0;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $this->calls++;

        return ['title' => 'Laravel SEO', 'description' => 'Tags'];
    }
}
