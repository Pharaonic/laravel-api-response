<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Feature;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Pharaonic\Laravel\ApiResponse\Response;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\Post;
use Pharaonic\Laravel\ApiResponse\Tests\TestCase;

final class EnvelopeTest extends TestCase
{
    public function test_a_success_sends_the_canonical_envelope(): void
    {
        $response = $this->finalize(Response::success([['id' => 1, 'title' => 'Laravel SEO']])->message('Posts retrieved successfully.'));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame('{"success":true,"message":"Posts retrieved successfully.","data":[{"id":1,"title":"Laravel SEO"}]}', $response->getContent());
    }

    public function test_an_error_sends_its_message_code_and_errors(): void
    {
        $response = $this->finalize(Response::error('Insufficient balance.', 422)->code('INSUFFICIENT_BALANCE')->errors(['amount' => ['Too high.']]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame([
            'success' => false,
            'message' => 'Insufficient balance.',
            'code' => 'INSUFFICIENT_BALANCE',
            'errors' => ['amount' => ['Too high.']],
        ], $this->body($response));
    }

    public function test_the_status_helpers_set_status_flag_and_code(): void
    {
        $cases = [
            [Response::success(), 200, true, null],
            [Response::created(['id' => 1]), 201, true, null],
            [Response::accepted(), 202, true, null],
            [Response::error('Bad.'), 400, false, null],
            [Response::unauthorized(), 401, false, 'UNAUTHENTICATED'],
            [Response::forbidden(), 403, false, 'FORBIDDEN'],
            [Response::notFound(), 404, false, 'NOT_FOUND'],
            [Response::validationError(['email' => ['Required.']]), 422, false, 'VALIDATION_ERROR'],
        ];

        foreach ($cases as [$builder, $status, $success, $code]) {
            $response = $this->finalize($builder);
            $body = $this->body($response);

            $this->assertSame($status, $response->getStatusCode());
            $this->assertSame($success, $body['success']);
            $this->assertSame($code, $body['code'] ?? null);
        }
    }

    public function test_validation_error_sends_the_canonical_shape(): void
    {
        $body = $this->body($this->finalize(Response::validationError(['email' => ['The email field is required.']])));

        $this->assertSame([
            'success' => false,
            'message' => 'The given data was invalid.',
            'code' => 'VALIDATION_ERROR',
            'errors' => ['email' => ['The email field is required.']],
        ], $body);
    }

    public function test_unset_fields_are_left_out_but_values_the_caller_set_are_kept(): void
    {
        $this->assertSame(['success' => true], $this->body($this->finalize(Response::success())));

        foreach ([null, [], false, 0, '', '0'] as $value) {
            $body = $this->body($this->finalize(Response::success($value)));

            $this->assertArrayHasKey('data', $body);
            $this->assertSame($value, $body['data']);
        }

        $this->assertSame(['success' => true, 'message' => ''], $this->body($this->finalize(Response::success()->message(''))));
        $this->assertSame(['success' => false, 'message' => 'x', 'errors' => []], $this->body($this->finalize(Response::error('x')->errors([]))));
    }

    public function test_an_empty_collection_stays_an_empty_list(): void
    {
        $this->posts(0);

        $this->assertSame([], $this->body($this->finalize(Response::success(Post::query()->get())))['data']);
        $this->assertSame([], $this->body($this->finalize(Response::success(new Collection)))['data']);
    }

    public function test_models_keep_their_serialization_rules(): void
    {
        $this->posts(1);

        $body = $this->body($this->finalize(Response::success(Post::query()->first())));

        $this->assertSame(['id' => 1, 'title' => 'Post 1', 'comments' => 0], $body['data']);
        $this->assertArrayNotHasKey('secret', $body['data']);
    }

    public function test_json_serializable_arrayable_and_scalars_are_resolved(): void
    {
        $json = new class implements \JsonSerializable
        {
            public function jsonSerialize(): mixed
            {
                return ['json' => true];
            }
        };

        $this->assertSame(['json' => true], $this->body($this->finalize(Response::success($json)))['data']);
        $this->assertSame([1, 2], $this->body($this->finalize(Response::success(new Collection([1, 2]))))['data']);
        $this->assertSame('text', $this->body($this->finalize(Response::success('text')))['data']);
        $this->assertSame(1.5, $this->body($this->finalize(Response::success(1.5)))['data']);
    }

    public function test_fluent_calls_give_the_same_response_in_any_order(): void
    {
        $a = Response::success(['a' => 1])->message('m')->meta('version', '1.0')->notify('n', 'success')->header('X-A', '1');
        $b = Response::success()->header('X-A', '1')->notify('n', 'success')->meta('version', '1.0')->data(['a' => 1])->message('m');

        $first = $this->finalize($a);
        $second = $this->finalize($b);

        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertSame('1', $second->headers->get('X-A'));
    }

    public function test_repeated_setters_keep_the_last_value_and_meta_merges(): void
    {
        $body = $this->body($this->finalize(
            Response::success(1)->data(2)->message('a')->message('b')->code('A')->code('B')
                ->meta('one', 1)->meta(['two' => 2, 'one' => 'replaced'])
        ));

        $this->assertSame(2, $body['data']);
        $this->assertSame('b', $body['message']);
        $this->assertSame('B', $body['code']);
        $this->assertSame(['one' => 'replaced', 'two' => 2], $body['meta']);
    }

    public function test_finalizing_twice_gives_the_same_response(): void
    {
        $builder = Response::success([1])->notify('Once.')->meta('k', 'v');

        $first = $this->finalize($builder);
        $second = $this->finalize($builder);

        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertCount(1, $this->body($second)['notifications']);
    }

    public function test_it_works_as_a_controller_return_value(): void
    {
        $this->app['router']->get('api/posts', fn () => Response::created(['id' => 7])->message('Created.')->header('X-API-Version', '1'));

        $this->getJson('api/posts')
            ->assertStatus(201)
            ->assertHeader('X-API-Version', '1')
            ->assertExactJson(['success' => true, 'message' => 'Created.', 'data' => ['id' => 7]]);
    }

    public function test_response_finalizes_for_the_current_request(): void
    {
        $this->app['router']->get('api/direct', fn () => Response::success('ok')->response());

        $this->getJson('api/direct')->assertOk()->assertExactJson(['success' => true, 'data' => 'ok']);
    }

    public function test_status_must_agree_with_the_kind_of_response(): void
    {
        foreach ([
            fn () => Response::success()->status(422),
            fn () => Response::error('x', 200),
            fn () => Response::error('x')->status(302),
            fn () => Response::success()->status(100),
            fn () => Response::success()->status(600),
        ] as $call) {
            try {
                $call();
                $this->fail('An invalid status was accepted.');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('status', $e->getMessage());
            }
        }

        $this->assertTrue($this->body($this->finalize(Response::success()->status(302)))['success']);
    }

    public function test_fields_must_agree_with_the_kind_of_response(): void
    {
        foreach ([
            fn () => Response::error('x')->data(['id' => 1]),
            fn () => Response::notFound()->data(null),
            fn () => Response::success()->errors(['email' => ['Invalid.']]),
            fn () => Response::created(['id' => 1])->errors([]),
        ] as $call) {
            try {
                $call();
                $this->fail('A field the response cannot carry was accepted.');
            } catch (InvalidArgumentException $e) {
                $this->assertMatchesRegularExpression('/cannot carry (data|errors)/', $e->getMessage());
            }
        }

        $this->assertSame(['success' => true], $this->body($this->finalize(Response::success()->errors(null))));
        $this->assertSame(
            ['success' => false, 'message' => 'x', 'meta' => ['retry_after' => 30], 'notifications' => [['type' => 'error', 'message' => 'Try later.']]],
            $this->body($this->finalize(Response::error('x')->meta('retry_after', 30)->notify('Try later.', 'error')->withoutRequestId()))
        );
    }

    public function test_the_error_code_is_validated(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Response::error('x')->code("BAD\nCODE");
    }

    public function test_no_content_sends_no_body_and_no_envelope(): void
    {
        $response = $this->finalize(Response::noContent()->notify('Ignored.')->meta('k', 'v')->header('X-Kept', 'yes'));

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
        $this->assertSame('yes', $response->headers->get('X-Kept'));
        $this->assertNotInstanceOf(JsonResponse::class, $response);
    }

    public function test_a_304_sends_no_body(): void
    {
        $response = $this->finalize(Response::success(['a' => 1])->status(304));

        $this->assertSame(304, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    public function test_a_head_request_gets_headers_without_a_body(): void
    {
        $response = $this->finalize(Response::success(['a' => 1])->header('X-A', '1'), '/api/posts', 'HEAD');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
        $this->assertSame('1', $response->headers->get('X-A'));
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }

    public function test_through_the_kernel_204_and_head_stay_bodyless(): void
    {
        $this->app['router']->get('api/empty', fn () => Response::noContent());
        $this->app['router']->get('api/head', fn () => Response::success(['a' => 1]));

        $this->assertSame('', $this->getJson('api/empty')->assertStatus(204)->getContent());
        $this->assertSame('', $this->call('HEAD', 'api/head')->assertOk()->getContent());
    }
}
