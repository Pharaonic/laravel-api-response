<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Fixtures;

use Pharaonic\Laravel\ApiResponse\Profile;

class MobileProfile extends Profile
{
    public function fields(): array
    {
        return ['data' => 'result', 'message' => 'msg'];
    }

    public function requestIdInBody(): bool
    {
        return false;
    }
}
