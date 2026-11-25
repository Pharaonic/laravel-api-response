<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Support;

use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use Pharaonic\Laravel\ApiResponse\Contracts\ExceptionMapper;
use Pharaonic\Laravel\ApiResponse\Contracts\PaginationAdapter;
use Pharaonic\Laravel\ApiResponse\Profiles\Profile;

/**
 * The package configuration, validated where it is read.
 *
 * @internal
 */
final class Settings
{
    public const FIELDS = ['success', 'message', 'code', 'data', 'errors', 'meta', 'notifications', 'seo', 'request_id'];

    public function __construct(private Repository $config)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config->get('pharaonic.api-response.'.$key, $default);
    }

    public function defaultProfile(): string
    {
        $name = $this->get('default_profile', 'default');

        if (! is_string($name) || $name === '') {
            throw $this->invalid('default_profile', 'must be the name of a registered profile');
        }

        return $name;
    }

    /**
     * @return class-string<Profile>
     */
    public function profileClass(string $name): string
    {
        $profiles = $this->get('profiles', []);
        $class = is_array($profiles) ? ($profiles[$name] ?? null) : null;

        if ($class === null) {
            throw new InvalidArgumentException("The response profile [{$name}] is not registered in pharaonic.api-response.profiles.");
        }

        if (! is_string($class) || ! is_a($class, Profile::class, true)) {
            throw $this->invalid("profiles.{$name}", 'must be a class that extends '.Profile::class);
        }

        return $class;
    }

    public function hasProfile(string $name): bool
    {
        $profiles = $this->get('profiles', []);

        return is_array($profiles) && array_key_exists($name, $profiles);
    }

    /**
     * Check a profile's field names: every canonical field, each with a unique, non-empty name.
     *
     * @param  array<mixed>  $fields
     * @return array<string, string>
     */
    public function fields(array $fields): array
    {
        $names = [];

        foreach (self::FIELDS as $field) {
            $name = $fields[$field] ?? null;

            if (! is_string($name) || $name === '') {
                throw $this->invalid("envelope.fields.{$field}", 'must be a non-empty string');
            }

            if (in_array($name, $names, true)) {
                throw $this->invalid('envelope.fields', "sends two fields under the name [{$name}]");
            }

            $names[$field] = $name;
        }

        if ($unknown = array_diff(array_keys($fields), self::FIELDS)) {
            throw $this->invalid('envelope.fields', 'has unknown fields: '.implode(', ', array_map('strval', $unknown)));
        }

        return $names;
    }

    public function jsonOptions(): int
    {
        $options = $this->get('json_options', 0);

        if (! is_int($options) || $options < 0) {
            throw $this->invalid('json_options', 'must be an integer of JSON_* flags');
        }

        if ($options & JSON_PARTIAL_OUTPUT_ON_ERROR) {
            throw $this->invalid('json_options', 'cannot contain JSON_PARTIAL_OUTPUT_ON_ERROR: a response must never be sent incomplete');
        }

        return $options | JSON_THROW_ON_ERROR;
    }

    /**
     * @return list<string>
     */
    public function notificationTypes(): array
    {
        return $this->strings('notifications.types', ['success', 'error', 'warning', 'info']);
    }

    public function defaultNotificationType(): string
    {
        $type = $this->get('notifications.default_type', 'info');

        if (! is_string($type) || ! in_array($type, $this->notificationTypes(), true)) {
            throw $this->invalid('notifications.default_type', 'must be one of notifications.types');
        }

        return $type;
    }

    public function missingSeo(): string
    {
        $policy = $this->get('seo.missing_driver', 'throw');

        if (! in_array($policy, ['throw', 'ignore'], true)) {
            throw $this->invalid('seo.missing_driver', 'must be "throw" or "ignore"');
        }

        return $policy;
    }

    public function requestIdHeader(): string
    {
        $header = $this->get('request_id.header', 'X-Request-ID');

        if (! is_string($header) || ! Headers::validName($header)) {
            throw $this->invalid('request_id.header', 'must be a valid header name');
        }

        return $header;
    }

    public function requestIdGenerator(): string
    {
        $generator = $this->get('request_id.generator', 'uuid');

        if ($generator !== 'uuid' && (! is_string($generator) || ! class_exists($generator) || ! method_exists($generator, '__invoke'))) {
            throw $this->invalid('request_id.generator', 'must be "uuid" or an invokable class that returns the ID');
        }

        return $generator;
    }

    public function requestIdMaxLength(): int
    {
        $length = $this->get('request_id.max_length', 128);

        if (! is_int($length) || $length < 1 || $length > 1024) {
            throw $this->invalid('request_id.max_length', 'must be an integer between 1 and 1024');
        }

        return $length;
    }

    public function queryStringEnabled(): bool
    {
        return (bool) $this->get('pagination.query_string.enabled', true);
    }

    /**
     * @return list<string>
     */
    public function queryStringExcept(): array
    {
        return $this->strings('pagination.query_string.except', []);
    }

    /**
     * @return list<class-string<PaginationAdapter>>
     */
    public function adapters(): array
    {
        $adapters = [];

        foreach ($this->strings('pagination.adapters', []) as $adapter) {
            if (! is_a($adapter, PaginationAdapter::class, true)) {
                throw $this->invalid('pagination.adapters', "contains [{$adapter}], which does not implement ".PaginationAdapter::class);
            }

            $adapters[] = $adapter;
        }

        return $adapters;
    }

    /**
     * @return list<string>
     */
    public function exceptionPaths(): array
    {
        return $this->strings('exceptions.paths', []);
    }

    /**
     * @return array<string, class-string<ExceptionMapper>>
     */
    public function exceptionMappers(): array
    {
        $mappers = $this->get('exceptions.mappers', []);

        if (! is_array($mappers)) {
            throw $this->invalid('exceptions.mappers', 'must map exception classes to mapper classes');
        }

        $valid = [];

        foreach ($mappers as $exception => $mapper) {
            if (! is_string($exception) || (! class_exists($exception) && ! interface_exists($exception))) {
                throw $this->invalid('exceptions.mappers', 'has a key that is not an exception class or interface');
            }

            if (! is_string($mapper) || ! is_a($mapper, ExceptionMapper::class, true)) {
                throw $this->invalid("exceptions.mappers.{$exception}", 'must implement '.ExceptionMapper::class);
            }

            $valid[$exception] = $mapper;
        }

        return $valid;
    }

    public function negotiates(): bool
    {
        return (bool) $this->get('content_negotiation.enabled', false);
    }

    /**
     * @return array<string, string>
     */
    public function mediaTypes(): array
    {
        $types = $this->get('content_negotiation.media_types', []);

        if (! is_array($types)) {
            throw $this->invalid('content_negotiation.media_types', 'must map media types to profile names');
        }

        $valid = [];

        foreach ($types as $type => $profile) {
            if (! is_string($type) || ! is_string($profile) || ! $this->hasProfile($profile)) {
                throw $this->invalid('content_negotiation.media_types', 'must map media types to registered profile names');
            }

            $valid[strtolower($type)] = $profile;
        }

        return $valid;
    }

    /**
     * @param  list<string>  $default
     * @return list<string>
     */
    private function strings(string $key, array $default): array
    {
        $values = $this->get($key, $default);

        if (! is_array($values)) {
            throw $this->invalid($key, 'must be a list of strings');
        }

        foreach ($values as $value) {
            if (! is_string($value) || $value === '') {
                throw $this->invalid($key, 'must be a list of non-empty strings');
            }
        }

        /** @var list<string> */
        return array_values($values);
    }

    private function invalid(string $key, string $reason): InvalidArgumentException
    {
        return new InvalidArgumentException("pharaonic.api-response.{$key} {$reason}.");
    }
}
