<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Feature;

use InvalidArgumentException;
use JsonException;
use Pharaonic\Laravel\ApiResponse\Response;
use Pharaonic\Laravel\ApiResponse\Tests\TestCase;

final class ExtensionTest extends TestCase
{
    public function test_headers_are_sent_and_the_response_wins_over_the_profile(): void
    {
        $response = $this->finalize(Response::success()->header('X-One', '1')->withHeaders(['X-Two' => '2', 'X-One' => 'last']));

        $this->assertSame('last', $response->headers->get('X-One'));
        $this->assertSame('2', $response->headers->get('X-Two'));
    }

    public function test_unsafe_headers_are_refused(): void
    {
        foreach ([['Bad Name', 'v'], ['X-Ok', "split\r\nSet-Cookie: x=1"], ['X-Ok', "nul\0"], ['', 'v']] as [$name, $value]) {
            try {
                Response::success()->header($name, $value);
                $this->fail("The header [{$name}] was accepted.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_macros_extend_the_builder(): void
    {
        Response::macro('version', function (string $version) {
            /** @var Response $this */
            return $this->header('X-API-Version', $version)->meta('version', $version);
        });

        $response = $this->finalize(Response::success()->version('2'));

        $this->assertSame('2', $response->headers->get('X-API-Version'));
        $this->assertSame(['version' => '2'], $this->body($response)['meta']);
        $this->assertTrue(Response::hasMacro('version'));
    }

    public function test_a_macro_cannot_shadow_a_method(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[message]');

        Response::macro('message', fn () => null);
    }

    public function test_macros_are_flushed_between_tests(): void
    {
        $this->assertFalse(Response::hasMacro('version'));
    }

    public function test_when_and_unless_change_the_builder_state(): void
    {
        $body = $this->body($this->finalize(
            Response::success()
                ->when(true, fn (Response $r) => $r->meta('a', 1))
                ->when(false, fn (Response $r) => $r->meta('b', 1), fn (Response $r) => $r->meta('b', 'default'))
                ->when(fn () => 'value', fn (Response $r, $value) => $r->meta('c', $value))
                ->unless(false, fn (Response $r) => $r->meta('d', 1))
                ->unless(true, fn (Response $r) => $r->meta('e', 1))
        ));

        $this->assertSame(['a' => 1, 'b' => 'default', 'c' => 'value', 'd' => 1], $body['meta']);
    }

    public function test_transform_changes_only_the_payload_in_order(): void
    {
        $body = $this->body($this->finalize(
            Response::success(['a' => 1])
                ->meta('kept', true)
                ->transform(fn (array $data) => $data + ['b' => 2])
                ->transform(fn (array $data) => array_reverse($data, true))
        ));

        $this->assertSame(['b' => 2, 'a' => 1], $body['data']);
        $this->assertSame(['kept' => true], $body['meta']);
    }

    public function test_json_options_follow_the_configuration(): void
    {
        $data = ['text' => 'مرحبا', 'url' => 'https://pharaonic.dev/a'];

        $this->assertSame('{"success":true,"data":{"text":"مرحبا","url":"https://pharaonic.dev/a"}}', $this->finalize(Response::success($data))->getContent());

        $this->configure(['json_options' => 0]);
        $this->assertStringNotContainsString('مرحبا', (string) $this->finalize(Response::success($data))->getContent());
        $this->assertStringContainsString('https:\/\/', (string) $this->finalize(Response::success($data))->getContent());

        $this->configure(['json_options' => JSON_PRETTY_PRINT]);
        $this->assertStringContainsString("\n", (string) $this->finalize(Response::success($data))->getContent());
    }

    public function test_invalid_utf8_fails_instead_of_sending_a_false_success(): void
    {
        $this->expectException(JsonException::class);

        $this->finalize(Response::success("\xB1\x31"));
    }

    public function test_recursion_and_unsupported_values_fail(): void
    {
        $loop = [];
        $loop['self'] = &$loop;

        foreach ([$loop, NAN, fopen('php://memory', 'r')] as $value) {
            try {
                $this->finalize(Response::success($value));
                $this->fail('An unencodable value was sent.');
            } catch (JsonException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_partial_output_on_error_is_refused(): void
    {
        $this->configure(['json_options' => JSON_PARTIAL_OUTPUT_ON_ERROR]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JSON_PARTIAL_OUTPUT_ON_ERROR');

        $this->finalize(Response::success());
    }

    public function test_the_pagination_meta_key_belongs_to_the_package(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Response::success()->meta('pagination', []);
    }
}
