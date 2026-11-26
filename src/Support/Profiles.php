<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Pharaonic\Laravel\ApiResponse\Profile;

/**
 * Builds a profile of pharaonic.api-response.profiles and checks its field names.
 *
 * @internal
 */
final class Profiles
{
    public const FIELDS = ['success', 'message', 'code', 'data', 'errors', 'meta', 'notifications', 'seo', 'request_id'];

    /**
     * @return array{fields: array<string, string>, request_id_in_body: bool}
     */
    public static function resolve(Container $container, Repository $config, ?string $name): array
    {
        $default = $config->get('pharaonic.api-response.default_profile', 'default');
        $name ??= is_string($default) ? $default : get_debug_type($default);
        $profiles = $config->get('pharaonic.api-response.profiles', []);
        $class = is_array($profiles) ? ($profiles[$name] ?? null) : null;

        if ($class === null) {
            throw new InvalidArgumentException("The response profile [{$name}] is not defined in pharaonic.api-response.profiles.");
        }

        if (! is_string($class) || ! is_a($class, Profile::class, true)) {
            throw new InvalidArgumentException("The response profile [{$name}] must be a class that extends ".Profile::class.'.');
        }

        /** @var Profile $profile */
        $profile = $container->make($class);
        $fields = array_combine(self::FIELDS, self::FIELDS);

        foreach ($profile->fields() as $field => $label) {
            if (! isset($fields[$field])) {
                throw new InvalidArgumentException("The response profile [{$name}] renames an unknown field [{$field}].");
            }

            if ($label === '') {
                throw new InvalidArgumentException("The response profile [{$name}] must name the [{$field}] field with a non-empty string.");
            }

            $fields[$field] = $label;
        }

        if (count(array_unique($fields)) !== count($fields)) {
            throw new InvalidArgumentException("The response profile [{$name}] sends two fields under the same name.");
        }

        return ['fields' => $fields, 'request_id_in_body' => $profile->requestIdInBody()];
    }
}
