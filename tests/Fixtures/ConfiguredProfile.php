<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Fixtures;

use Illuminate\Contracts\Config\Repository;
use Pharaonic\Laravel\ApiResponse\Profile;

/**
 * Takes its field names from the container, to prove profiles are built by it.
 */
class ConfiguredProfile extends Profile
{
    public function __construct(private Repository $config) {}

    public function fields(): array
    {
        $fields = $this->config->get('testing.profile_fields', []);

        /** @var array<string, string> */
        return is_array($fields) ? $fields : [];
    }
}
