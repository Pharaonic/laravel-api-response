<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Fixtures;

use Pharaonic\Laravel\ApiResponse\Profiles\Profile;

/**
 * Sends the data alone, without an envelope.
 */
class RawProfile extends Profile
{
    public function body(array $parts): mixed
    {
        return $parts['data'] ?? null;
    }

    public function paginates(): bool
    {
        return false;
    }
}
