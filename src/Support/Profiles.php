<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Support;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Pharaonic\Laravel\ApiResponse\Profiles\Profile;
use Symfony\Component\HttpFoundation\AcceptHeader;

/**
 * Chooses a response's profile: the response's own choice, then the route's
 * (UseResponseProfile), then the negotiated media type, then the default.
 *
 * @internal
 */
final class Profiles
{
    public const ATTRIBUTE = 'pharaonic.api-response.profile';

    public function __construct(private Container $container, private Settings $settings)
    {
    }

    /**
     * @return array{profile: Profile, fields: array<string, string>, media_type: ?string}
     */
    public function resolve(?string $explicit, Request $request): array
    {
        $mediaType = null;
        $name = $explicit;

        if ($name === null) {
            $route = $request->attributes->get(self::ATTRIBUTE);
            $name = is_string($route) ? $route : null;
        }

        if ($name === null && $this->settings->negotiates()) {
            [$name, $mediaType] = $this->negotiate($request);
        }

        $class = $this->settings->profileClass($name ?? $this->settings->defaultProfile());
        $profile = $this->container->make($class);

        /** @var Profile $profile */
        return [
            'profile' => $profile,
            'fields' => $this->settings->fields($profile->fields()),
            'media_type' => $mediaType,
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function negotiate(Request $request): array
    {
        $types = $this->settings->mediaTypes();

        foreach (AcceptHeader::fromString($request->headers->get('Accept') ?? '')->all() as $item) {
            $value = strtolower($item->getValue());

            if ($item->getQuality() > 0 && isset($types[$value])) {
                return [$types[$value], $value];
            }
        }

        return [null, null];
    }
}
