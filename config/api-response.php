<?php

declare(strict_types=1);

use Pharaonic\Laravel\ApiResponse\Profile;

return [

    /*
    | The profile a response uses unless it calls profile().
    */
    'default_profile' => 'default',

    /*
    | Profile names and their classes. A profile class extends
    | Pharaonic\Laravel\ApiResponse\Profile and overrides only what differs:
    | the field names, and whether the body carries the request ID.
    | A profile never adds SEO tags; only withSEO() does.
    */
    'profiles' => [
        'default' => Profile::class,

        // 'mobile' => App\Http\Profiles\MobileProfile::class,
    ],

    'pagination' => [

        // Keep the request's query string in pagination links unless a response says otherwise.
        'query_string' => true,

        // Query keys never copied into pagination links.
        'sensitive_query' => ['token', 'api_key', 'access_token', 'password'],
    ],

    'exceptions' => [

        // The registered ExceptionRenderer renders requests that expect JSON and requests to these paths.
        'paths' => ['api/*'],
    ],

    'request_id' => [

        // The header AssignRequestId sends the ID in, and reads it from when trust_incoming is on.
        'header' => 'X-Request-ID',

        // Reuse a valid incoming ID (1 to 128 letters, digits, '.', '_', ':' or '-') instead of generating one.
        'trust_incoming' => false,

        // Add the ID to extra.request_id of the default log channel's records.
        'logging' => true,
    ],

];
