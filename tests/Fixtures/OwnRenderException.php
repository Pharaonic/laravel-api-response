<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Fixtures;

use Illuminate\Http\Response;
use RuntimeException;

class OwnRenderException extends RuntimeException
{
    public function render(): Response
    {
        return new Response('own render', 418);
    }
}
