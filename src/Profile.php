<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse;

/**
 * How a response is shaped. Extend it, override only what differs, and
 * register the class under a name in pharaonic.api-response.profiles.
 * Profiles are built by the container for each response, so they may
 * type-hint dependencies. A profile never adds SEO tags; only withSEO() does.
 */
class Profile
{
    /**
     * The envelope fields this profile renames: canonical name => sent name.
     * The canonical names are success, message, data, errors, meta,
     * notifications, seo and request_id; the others keep their own name.
     *
     * @return array<string, string>
     */
    public function fields(): array
    {
        return [];
    }

    /**
     * Whether the body carries request_id when the request has one.
     */
    public function requestIdInBody(): bool
    {
        return true;
    }
}
