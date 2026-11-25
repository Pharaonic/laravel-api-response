<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Support;

use Illuminate\Container\Container;
use Illuminate\Contracts\Translation\Translator;

/**
 * The package's own messages, translated in the current locale. Each key in
 * api-response::messages falls back to its English text when the application
 * has no translator or no line for it.
 *
 * @internal
 */
final class Messages
{
    /**
     * Laravel's own English messages, matched exactly, and the key that translates each one.
     */
    private const FRAMEWORK = [
        'Unauthenticated.' => 'unauthenticated',
        'This action is unauthorized.' => 'forbidden',
        'The given data was invalid.' => 'validation',
        'Too Many Attempts.' => 'too_many_attempts',
        'CSRF token mismatch.' => 'page_expired',
    ];

    public static function get(string $key, string $fallback): string
    {
        $container = Container::getInstance();

        if (! $container->bound('translator')) {
            return $fallback;
        }

        $translator = $container->make('translator');

        if (! $translator instanceof Translator) {
            return $fallback;
        }

        $line = 'api-response::messages.'.$key;
        $value = $translator->get($line);

        return is_string($value) && $value !== $line ? $value : $fallback;
    }

    /**
     * Translate a message Laravel set in English; any other message is the application's own and is kept.
     */
    public static function framework(string $message): string
    {
        return isset(self::FRAMEWORK[$message]) ? self::get(self::FRAMEWORK[$message], $message) : $message;
    }
}
