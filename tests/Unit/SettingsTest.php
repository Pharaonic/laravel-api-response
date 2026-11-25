<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Unit;

use InvalidArgumentException;
use Pharaonic\Laravel\ApiResponse\Support\Settings;
use Pharaonic\Laravel\ApiResponse\Tests\TestCase;

final class SettingsTest extends TestCase
{
    private function settings(): Settings
    {
        return new Settings($this->app['config']);
    }

    public function test_the_defaults_are_valid(): void
    {
        $settings = $this->settings();

        $this->assertSame('default', $settings->defaultProfile());
        $this->assertSame(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR, $settings->jsonOptions());
        $this->assertSame('info', $settings->defaultNotificationType());
        $this->assertSame('throw', $settings->missingSeo());
        $this->assertSame('X-Request-ID', $settings->requestIdHeader());
        $this->assertSame('uuid', $settings->requestIdGenerator());
        $this->assertSame([], $settings->adapters());
        $this->assertSame([], $settings->exceptionMappers());
        $this->assertSame(['api/*'], $settings->exceptionPaths());
        $this->assertFalse($settings->negotiates());
        $this->assertCount(9, $settings->fields((array) config('pharaonic.api-response.envelope.fields')));
    }

    public function test_invalid_values_are_refused_with_their_key(): void
    {
        $cases = [
            ['json_options', 'pretty', fn (Settings $s) => $s->jsonOptions(), 'json_options'],
            ['json_options', -1, fn (Settings $s) => $s->jsonOptions(), 'json_options'],
            ['seo.missing_driver', 'warn', fn (Settings $s) => $s->missingSeo(), 'seo.missing_driver'],
            ['request_id.header', "X-Id\r\n", fn (Settings $s) => $s->requestIdHeader(), 'request_id.header'],
            ['request_id.max_length', 0, fn (Settings $s) => $s->requestIdMaxLength(), 'request_id.max_length'],
            ['pagination.adapters', [\stdClass::class], fn (Settings $s) => $s->adapters(), 'pagination.adapters'],
            ['pagination.query_string.except', 'token', fn (Settings $s) => $s->queryStringExcept(), 'pagination.query_string.except'],
            ['exceptions.mappers', ['NoSuchException' => 'X'], fn (Settings $s) => $s->exceptionMappers(), 'exceptions.mappers'],
            ['content_negotiation.media_types', ['application/x' => 'missing'], fn (Settings $s) => $s->mediaTypes(), 'content_negotiation.media_types'],
            ['default_profile', '', fn (Settings $s) => $s->defaultProfile(), 'default_profile'],
            ['notifications.types', ['info', 3], fn (Settings $s) => $s->notificationTypes(), 'notifications.types'],
        ];

        foreach ($cases as [$key, $value, $read, $message]) {
            $this->refreshApplication();
            $this->configure([$key => $value]);

            try {
                $read($this->settings());
                $this->fail("[{$key}] accepted an invalid value.");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString("pharaonic.api-response.{$message}", $e->getMessage());
            }
        }
    }

    public function test_envelope_fields_must_be_complete_known_and_unique(): void
    {
        $fields = (array) config('pharaonic.api-response.envelope.fields');

        foreach ([
            array_diff_key($fields, ['seo' => true]),
            $fields + ['extra' => 'extra'],
            array_merge($fields, ['data' => '']),
            array_merge($fields, ['meta' => 'data']),
        ] as $invalid) {
            try {
                $this->settings()->fields($invalid);
                $this->fail('Invalid envelope fields were accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
