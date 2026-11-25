<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Contracts;

use Illuminate\Http\Request;
use Pharaonic\Laravel\ApiResponse\Response;
use Throwable;

/**
 * Turns an exception into an API response. Register it under
 * exceptions.mappers for an exception class or interface; the mapper of the
 * most specific class wins. Return null to fall back to the next mapping.
 */
interface ExceptionMapper
{
    public function map(Throwable $exception, Request $request): ?Response;
}
