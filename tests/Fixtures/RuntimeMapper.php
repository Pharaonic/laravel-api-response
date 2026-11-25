<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Fixtures;

use Illuminate\Http\Request;
use Pharaonic\Laravel\ApiResponse\Contracts\ExceptionMapper;
use Pharaonic\Laravel\ApiResponse\Response;
use Throwable;

class RuntimeMapper implements ExceptionMapper
{
    public function map(Throwable $exception, Request $request): ?Response
    {
        return Response::error('Runtime failure.', 500)->code('RUNTIME');
    }
}
