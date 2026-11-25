<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Fixtures;

class UnsafeGenerator
{
    public function __invoke(): string
    {
        return "id\r\nSet-Cookie: x=1";
    }
}
