<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Fixtures;

use Closure;
use Illuminate\Http\Request;
use Pharaonic\Laravel\ApiResponse\Response;

/**
 * A middleware that shares metadata the way the documentation shows.
 */
class ShareApiVersion
{
    public function handle(Request $request, Closure $next): mixed
    {
        Response::shareMeta('version', '1.0');
        Response::shareMeta(['region' => 'eu']);

        return $next($request);
    }
}
