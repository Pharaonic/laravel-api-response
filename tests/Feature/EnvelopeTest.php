<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Feature;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Pharaonic\Laravel\ApiResponse\Response;
use Pharaonic\Laravel\ApiResponse\Tests\TestCase;

final class EnvelopeTest extends TestCase
{
    public function test_a_success_sends_success_message_and_data(): void
    {
        $response = $this->build(Response::success(['id' => 1]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame(['success' => true, 'message' => null, 'data' => ['id' => 1], 'meta' => null], $this->body($response));
    }

    public function test_an_error_sends_success_message_and_errors(): void
    {
        $response = $this->build(Response::error('Post not found.', 404));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(['success' => false, 'message' => 'Post not found.', 'errors' => null, 'meta' => null], $this->body($response));
    }

    public function test_an_error_defaults_to_400(): void
    {
        $this->assertSame(400, $this->build(Response::error('Bad.'))->getStatusCode());
    }

    public function test_validation_errors_are_sent_per_field(): void
    {
        $response = $this->build(
            Response::error('Validation failed.', 422)->errors(['email' => ['The email field is required.']])
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(['email' => ['The email field is required.']], $this->body($response)['errors']);
    }

    public function test_data_set_to_null_an_empty_array_false_or_zero_is_kept(): void
    {
        foreach ([null, [], false, 0, ''] as $value) {
            $body = $this->body($this->build(Response::success($value)));

            $this->assertArrayHasKey('data', $body);
            $this->assertSame($value, $body['data']);
        }
    }

    public function test_modifiers_can_be_called_in_any_order(): void
    {
        $first = Response::success(['id' => 1])->notify('Created.', 'success')->status(201)->message('Post created.');
        $second = Response::success(['id' => 1])->message('Post created.')->status(201)->notify('Created.', 'success');

        $this->assertSame($this->build($first)->getContent(), $this->build($second)->getContent());
        $this->assertSame(201, $this->build($first)->getStatusCode());
    }

    public function test_a_builder_can_be_built_twice(): void
    {
        $response = Response::success(['id' => 1])->notify('Once.');

        $this->assertSame($this->build($response)->getContent(), $this->build($response)->getContent());
    }

    public function test_it_is_responsable_from_a_route(): void
    {
        $this->app['router']->post('api/posts', fn () => Response::success(['id' => 1])->status(201)->message('Post created.'));

        $this->postJson('api/posts')
            ->assertStatus(201)
            ->assertExactJson(['success' => true, 'message' => 'Post created.', 'data' => ['id' => 1], 'meta' => null]);
    }

    public function test_response_builds_for_the_current_request(): void
    {
        $this->app->instance('request', Request::create('/api/posts'));

        $response = Response::success(['id' => 1])->response();

        $this->assertSame(['success' => true, 'message' => null, 'data' => ['id' => 1], 'meta' => null], $this->body($response));
    }

    public function test_204_and_205_have_no_body(): void
    {
        foreach ([204, 205] as $status) {
            $response = $this->build(Response::success(['ignored' => true])->status($status)->notify('Hidden.'));

            $this->assertSame($status, $response->getStatusCode());
            $this->assertSame('', $response->getContent());
        }
    }

    public function test_a_success_refuses_a_status_outside_2xx(): void
    {
        foreach ([199, 301, 400, 500] as $status) {
            try {
                Response::success()->status($status);
                $this->fail("Status {$status} was accepted for a success.");
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString((string) $status, $exception->getMessage());
            }
        }
    }

    public function test_an_error_refuses_a_status_outside_4xx_and_5xx(): void
    {
        foreach ([200, 204, 399, 600] as $status) {
            try {
                Response::error('Nope.', $status);
                $this->fail("Status {$status} was accepted for an error.");
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString((string) $status, $exception->getMessage());
            }
        }
    }

    public function test_a_success_refuses_errors(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Response::success()->errors(['field' => ['Nope.']]);
    }

    public function test_arrayable_and_jsonable_data_are_serialized(): void
    {
        $arrayable = new class implements Arrayable
        {
            public function toArray(): array
            {
                return ['from' => 'array'];
            }
        };

        $jsonable = new class implements Jsonable
        {
            public function toJson($options = 0): string
            {
                return '{"from":"json"}';
            }
        };

        $this->assertSame(['from' => 'array'], $this->body($this->build(Response::success($arrayable)))['data']);
        $this->assertSame(['from' => 'json'], $this->body($this->build(Response::success($jsonable)))['data']);
        $this->assertSame([1, 2], $this->body($this->build(Response::success(collect([1, 2]))))['data']);
    }

    public function test_an_ordinary_laravel_response_is_left_alone(): void
    {
        $this->app['router']->get('api/plain', fn () => ['plain' => true]);
        $this->app['router']->get('page', fn () => 'Hello');

        $this->getJson('api/plain')->assertExactJson(['plain' => true]);
        $this->get('page')->assertSee('Hello');
    }

    public function test_notifications_are_sent_in_order(): void
    {
        $body = $this->body($this->build(
            Response::success()->notify('Saved.', 'success')->notify('Check your email.')->notify('Quota low.', 'warning')
        ));

        $this->assertSame([
            ['type' => 'success', 'message' => 'Saved.'],
            ['type' => 'info', 'message' => 'Check your email.'],
            ['type' => 'warning', 'message' => 'Quota low.'],
        ], $body['notifications']);
    }

    public function test_an_error_can_notify(): void
    {
        $body = $this->body($this->build(Response::error('Failed.')->notify('Try again.', 'error')));

        $this->assertSame([['type' => 'error', 'message' => 'Try again.']], $body['notifications']);
    }

    public function test_an_unknown_notification_type_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[danger]');

        Response::success()->notify('Nope.', 'danger');
    }

    public function test_optional_fields_are_left_out_when_unused(): void
    {
        $this->assertSame(['success', 'message', 'data', 'meta'], array_keys($this->body($this->build(Response::success()))));
        $this->assertSame(['success', 'message', 'errors', 'meta'], array_keys($this->body($this->build(Response::error('Nope.')))));
    }

    public function test_meta_is_null_until_something_is_added(): void
    {
        $this->assertNull($this->body($this->build(Response::success()))['meta']);
        $this->assertNull($this->body($this->build(Response::error('Nope.')))['meta']);
    }

    public function test_meta_adds_one_key_or_many(): void
    {
        $body = $this->body($this->build(
            Response::success()->meta('version', '1.0')->meta(['count' => 3, 'version' => '2.0'])->meta('flags', [])
        ));

        $this->assertSame(['version' => '2.0', 'count' => 3, 'flags' => []], $body['meta']);
    }

    public function test_an_error_can_carry_meta(): void
    {
        $body = $this->body($this->build(Response::error('Insufficient balance.', 422)->meta('balance', 10)));

        $this->assertSame(['balance' => 10], $body['meta']);
    }

    public function test_the_pagination_meta_key_is_reserved(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[pagination]');

        Response::success()->meta('pagination', []);
    }
}
