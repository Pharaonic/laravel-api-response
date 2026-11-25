<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Support;

use Illuminate\Contracts\Container\Container;
use LogicException;

/**
 * Reads pharaonic/laravel-seo through seo()->toArray() (6.1 and later on
 * every line), without naming its classes.
 *
 * @internal
 */
final class Seo
{
    public const BINDING = 'pharaonic-seo';

    /**
     * @return array<array-key, mixed>|null null when the integration is missing and ignored
     */
    public static function tags(Container $container, Settings $settings): ?array
    {
        $policy = $settings->missingSeo();
        $manager = $container->bound(self::BINDING) ? $container->make(self::BINDING) : null;
        $export = is_object($manager) ? [$manager, 'toArray'] : null;

        if ($export !== null && is_callable($export)) {
            $tags = $export();

            return is_array($tags) ? $tags : [];
        }

        if ($policy === 'ignore') {
            return null;
        }

        throw new LogicException(
            'withSEO() needs pharaonic/laravel-seo 6.1 or later (seo()->toArray()). Install it with "composer require pharaonic/laravel-seo", '
            .'or set pharaonic.api-response.seo.missing_driver to "ignore".'
        );
    }
}
