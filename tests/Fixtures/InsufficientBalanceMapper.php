<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Fixtures;

use Illuminate\Http\Request;
use Pharaonic\Laravel\ApiResponse\Contracts\ExceptionMapper;
use Pharaonic\Laravel\ApiResponse\Response;
use Throwable;

class InsufficientBalanceMapper implements ExceptionMapper
{
    public function map(Throwable $exception, Request $request): ?Response
    {
        if (! $exception instanceof InsufficientBalance) {
            return null;
        }

        return Response::error($exception->getMessage(), 422)
            ->code('INSUFFICIENT_BALANCE')
            ->meta('balance', $exception->balance);
    }
}
