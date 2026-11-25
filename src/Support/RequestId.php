<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Support;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use UnexpectedValueException;

/**
 * @internal
 */
final class RequestId
{
    public const ATTRIBUTE = 'pharaonic.api-response.request_id';

    public static function of(Request $request): ?string
    {
        $id = $request->attributes->get(self::ATTRIBUTE);

        return is_string($id) ? $id : null;
    }

    public static function valid(string $id, int $maxLength): bool
    {
        return strlen($id) <= $maxLength && (bool) preg_match('/^[A-Za-z0-9._:\-]+$/', $id);
    }

    public static function generate(Container $container, string $generator, int $maxLength): string
    {
        if ($generator === 'uuid') {
            return Str::uuid()->toString();
        }

        $make = $container->make($generator);
        $id = is_callable($make) ? $make() : null;

        if (! is_string($id) || ! self::valid($id, $maxLength)) {
            throw new UnexpectedValueException("The request ID generator [{$generator}] must return 1 to {$maxLength} letters, digits, '.', '_', ':' or '-'.");
        }

        return $id;
    }
}
