<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Fixtures;

use Pharaonic\Laravel\ApiResponse\Profiles\Profile;

/**
 * A complete custom profile: renamed fields, a compact pagination, short
 * notifications, a default header and no request ID in the body.
 */
class MobileProfile extends Profile
{
    public function fields(): array
    {
        return array_merge(parent::fields(), ['success' => 'ok', 'data' => 'result']);
    }

    public function pagination(array $pagination): array
    {
        return ['type' => $pagination['type'], 'has_more' => $pagination['pages']['has_more'] ?? false];
    }

    public function notification(array $notification): array
    {
        return ['text' => $notification['message'], 'kind' => $notification['type']];
    }

    public function headers(): array
    {
        return ['X-Profile' => 'mobile', 'X-API-Version' => 'profile'];
    }

    public function includesRequestId(): bool
    {
        return false;
    }
}
