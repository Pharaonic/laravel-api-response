<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Support;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;

/**
 * The request ID lives in a request attribute, so it never outlives its
 * request. As a Monolog processor this class adds it to extra.request_id,
 * reading the current request when a record is written, so a long-running
 * worker never logs the ID of an earlier request.
 *
 * @internal
 */
final class RequestId
{
    public const ATTRIBUTE = 'pharaonic.api-response.request_id';

    public function __construct(private Container $container) {}

    public static function of(Request $request): ?string
    {
        $id = $request->attributes->get(self::ATTRIBUTE);

        return is_string($id) ? $id : null;
    }

    /**
     * An incoming ID is accepted only as 1 to 128 letters, digits, '.', '_', ':' or '-'.
     */
    public static function valid(string $id): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9._:\-]{1,128}$/', $id);
    }

    /**
     * Add the processor to the default log channel, once per Monolog instance.
     */
    public static function log(Container $container): void
    {
        if (! $container->bound('log')) {
            return;
        }

        $log = $container->make('log');
        $driver = is_object($log) && method_exists($log, 'driver') ? $log->driver() : $log;
        $monolog = is_object($driver) && method_exists($driver, 'getLogger') ? $driver->getLogger() : $driver;

        if (! is_object($monolog) || ! method_exists($monolog, 'pushProcessor') || ! method_exists($monolog, 'getProcessors')) {
            return;
        }

        $processors = $monolog->getProcessors();

        foreach (is_array($processors) ? $processors : [] as $processor) {
            if ($processor instanceof self) {
                return;
            }
        }

        $monolog->pushProcessor(new self($container));
    }

    /**
     * Monolog 1 and 2 pass an array, Monolog 3 a LogRecord.
     */
    public function __invoke(mixed $record): mixed
    {
        $request = $this->container->bound('request') ? $this->container->make('request') : null;
        $id = $request instanceof Request ? self::of($request) : null;

        if ($id === null) {
            return $record;
        }

        if (is_array($record)) {
            $record['extra'] = array_merge(is_array($record['extra'] ?? null) ? $record['extra'] : [], ['request_id' => $id]);
        } elseif (is_object($record) && property_exists($record, 'extra') && is_array($record->extra)) {
            $record->extra = array_merge($record->extra, ['request_id' => $id]);
        }

        return $record;
    }
}
