<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Pharaonic\Laravel\ApiResponse\Support\Profiles;
use Pharaonic\Laravel\ApiResponse\Support\Settings;

/**
 * Chooses the response profile of a route: UseResponseProfile::class.':mobile'.
 * A response that calls profile() still wins.
 */
final class UseResponseProfile
{
    public function __construct(private Repository $config)
    {
    }

    public function handle(Request $request, Closure $next, string $profile): mixed
    {
        if (! (new Settings($this->config))->hasProfile($profile)) {
            throw new InvalidArgumentException("The response profile [{$profile}] is not registered in pharaonic.api-response.profiles.");
        }

        $request->attributes->set(Profiles::ATTRIBUTE, $profile);

        return $next($request);
    }
}
