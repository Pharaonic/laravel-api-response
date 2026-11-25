<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Support;

use InvalidArgumentException;

/**
 * @internal
 */
final class Headers
{
    public static function validName(string $name): bool
    {
        return (bool) preg_match("/^[A-Za-z0-9!#$%&'*+.^_`|~-]+$/", $name);
    }

    public static function validValue(string $value): bool
    {
        return ! preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $value);
    }

    public static function validate(string $name, string $value): void
    {
        if (! self::validName($name)) {
            throw new InvalidArgumentException("[{$name}] is not a valid header name.");
        }

        if (! self::validValue($value)) {
            throw new InvalidArgumentException("The value of the [{$name}] header contains control characters.");
        }
    }
}
