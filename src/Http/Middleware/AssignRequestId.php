<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Pharaonic\Laravel\ApiResponse\Support\RequestId;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives the request a UUID, sends it back in a header and adds it to log
 * records. An incoming ID is reused only when trust_incoming is on and it is
 * valid.
 */
final class AssignRequestId
{
    public function __construct(private Container $container, private Repository $config)
    {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $header = $this->config->get('pharaonic.api-response.request_id.header', 'X-Request-ID');
        $header = is_string($header) && $header !== '' ? $header : 'X-Request-ID';
        $incoming = $request->headers->get($header);

        $id = $this->config->get('pharaonic.api-response.request_id.trust_incoming', false) === true
            && is_string($incoming)
            && RequestId::valid($incoming)
                ? $incoming
                : Str::uuid()->toString();

        $request->attributes->set(RequestId::ATTRIBUTE, $id);

        if ($this->config->get('pharaonic.api-response.request_id.logging', true)) {
            RequestId::log($this->container);
        }

        $response = $next($request);

        if ($response instanceof Response) {
            $response->headers->set($header, $id);
        }

        return $response;
    }
}
