<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Fixtures;

use RuntimeException;

class InsufficientBalance extends RuntimeException
{
    public function __construct(public int $balance)
    {
        parent::__construct('Insufficient balance.');
    }
}
