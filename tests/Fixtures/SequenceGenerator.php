<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Fixtures;

class SequenceGenerator
{
    private static int $next = 0;

    public function __invoke(): string
    {
        return 'req-'.++self::$next;
    }
}
