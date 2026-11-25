<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Profiles;

use Illuminate\Container\Container;

/**
 * How a response is shaped. Extend it and override only what differs; the
 * defaults follow the package configuration. Profiles are built by the
 * container for each response, so they may type-hint dependencies.
 */
class Profile
{
    /**
     * The name each envelope field is sent under, keyed by its canonical name.
     *
     * @return array<string, string>
     */
    public function fields(): array
    {
        /** @var array<string, string> $fields */
        $fields = (array) $this->config('envelope.fields', []);

        return $fields;
    }

    /**
     * Build the response body from its parts, keyed by canonical field name.
     * Only parts that are set are present. Override it to send another shape.
     *
     * @param  array<string, mixed>  $parts
     */
    public function body(array $parts): mixed
    {
        $fields = $this->fields();
        $body = [];

        foreach ($parts as $name => $value) {
            $body[$fields[$name] ?? $name] = $value;
        }

        return $body;
    }

    /**
     * Shape the normalized pagination metadata.
     *
     * @param  array<string, mixed>  $pagination
     * @return array<string, mixed>
     */
    public function pagination(array $pagination): array
    {
        return $pagination;
    }

    /**
     * Shape one notification. The default drops the optional fields that are not set.
     *
     * @param  array{type: string, message: string, title: ?string, duration: ?int, data: array<array-key, mixed>}  $notification
     * @return array<string, mixed>
     */
    public function notification(array $notification): array
    {
        return array_filter($notification, fn ($value) => $value !== null && $value !== []);
    }

    /**
     * Headers sent with every response of this profile. The response's own headers win.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return [];
    }

    /**
     * Whether the body carries the request ID when the response does not say.
     */
    public function includesRequestId(): bool
    {
        return (bool) $this->config('request_id.include_in_body', true);
    }

    /**
     * Whether paginators are sent with their pagination metadata.
     */
    public function paginates(): bool
    {
        return (bool) $this->config('pagination.enabled', true);
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        $config = Container::getInstance()->make('config');

        return is_object($config) && method_exists($config, 'get')
            ? $config->get('pharaonic.api-response.'.$key, $default)
            : $default;
    }
}
