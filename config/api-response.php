<?php

declare(strict_types=1);

use Pharaonic\Laravel\ApiResponse\Profiles\Profile;

return [

    /*
    | The profile a response uses when nothing else chooses one.
    */
    'default_profile' => 'default',

    /*
    | Profile names and their classes. A profile class extends
    | Pharaonic\Laravel\ApiResponse\Profiles\Profile.
    */
    'profiles' => [
        'default' => Profile::class,
    ],

    /*
    | Flags passed to json_encode(). JSON_THROW_ON_ERROR is always added, and
    | JSON_PARTIAL_OUTPUT_ON_ERROR is refused: a response that cannot be
    | encoded fails instead of being sent incomplete.
    */
    'json_options' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,

    /*
    | The name each envelope field is sent under. Names must be unique.
    */
    'envelope' => [
        'fields' => [
            'success' => 'success',
            'message' => 'message',
            'code' => 'code',
            'data' => 'data',
            'errors' => 'errors',
            'meta' => 'meta',
            'notifications' => 'notifications',
            'seo' => 'seo',
            'request_id' => 'request_id',
        ],
    ],

    'pagination' => [

        // Send paginators as their items plus meta.pagination.
        'enabled' => true,

        'query_string' => [

            // Keep the request's query string in pagination links by default.
            'enabled' => true,

            // Keys never copied into pagination links.
            'except' => ['token', 'api_key', 'access_token', 'password'],
        ],

        // Your own Contracts\PaginationAdapter classes, checked before the built-in ones.
        'adapters' => [],
    ],

    'notifications' => [
        'default_type' => 'info',
        'types' => ['success', 'error', 'warning', 'info'],
    ],

    'seo' => [

        // What withSEO() does without pharaonic/laravel-seo: 'throw' or 'ignore'.
        'missing_driver' => 'throw',
    ],

    'request_id' => [
        'header' => 'X-Request-ID',

        // 'uuid', or an invokable class that returns the ID.
        'generator' => 'uuid',

        // Reuse a valid incoming ID instead of generating one.
        'trust_incoming' => false,

        'max_length' => 128,

        // Add the ID to the response body unless the response says otherwise.
        'include_in_body' => true,

        // Share the ID with the log context (Laravel 8 and later).
        'logging' => true,
    ],

    'exceptions' => [

        // Requests matching these paths are rendered as JSON, as well as requests that expect JSON.
        'paths' => ['api/*'],

        // Exception class => Contracts\ExceptionMapper class.
        'mappers' => [],
    ],

    'content_negotiation' => [
        'enabled' => false,

        // Accept media type => profile name, e.g. 'application/vnd.acme.mobile+json' => 'mobile'.
        'media_types' => [],
    ],

];
