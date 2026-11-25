<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Fixtures;

use Illuminate\Http\JsonResponse;

/**
 * Shaped like pharaonic/laravel-api-idempotency's IdempotencyException: errorCode() and its own render().
 */
class RenderingCodedHttpException extends CodedHttpException
{
    public function render(): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage(), 'code' => $this->errorCode()], $this->getStatusCode(), $this->getHeaders());
    }
}
