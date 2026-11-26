<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Fixtures;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class OwnRenderException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return new JsonResponse(['own' => true], 409);
    }
}
