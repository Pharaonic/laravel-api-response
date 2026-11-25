<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Pharaonic\Laravel\ApiResponse\Support\RequestId;
use Pharaonic\Laravel\ApiResponse\Support\RequestIdProcessor;
use Pharaonic\Laravel\ApiResponse\Support\Settings;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives the request an ID, sends it back in a header, and adds it to log
 * records. An incoming ID is reused only when trust_incoming is on and it is
 * valid. The ID lives on the request, so nothing outlives it.
 */
final class AssignRequestId
{
    public function __construct(private Container $container, private Repository $config)
    {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $settings = new Settings($this->config);
        $header = $settings->requestIdHeader();
        $incoming = $request->headers->get($header);

        $maxLength = $settings->requestIdMaxLength();

        $id = $settings->get('request_id.trust_incoming', false) === true
            && is_string($incoming)
            && RequestId::valid($incoming, $maxLength)
                ? $incoming
                : RequestId::generate($this->container, $settings->requestIdGenerator(), $maxLength);

        $request->attributes->set(RequestId::ATTRIBUTE, $id);

        if ($settings->get('request_id.logging', true)) {
            RequestIdProcessor::register($this->container);
        }

        $response = $next($request);

        if ($response instanceof Response) {
            $response->headers->set($header, $id);
        }

        return $response;
    }
}
