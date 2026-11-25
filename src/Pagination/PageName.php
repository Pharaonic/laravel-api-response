<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Pagination;

/**
 * @internal
 */
final class PageName
{
    public static function of(object $paginator, string $method, string $default): string
    {
        $callback = [$paginator, $method];
        $name = is_callable($callback) ? $callback() : null;

        return is_string($name) && $name !== '' ? $name : $default;
    }
}
