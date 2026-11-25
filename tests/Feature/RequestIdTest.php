<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Feature;

use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Pharaonic\Laravel\ApiResponse\Http\Middleware\AssignRequestId;
use Pharaonic\Laravel\ApiResponse\Response;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\SequenceGenerator;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\UnsafeGenerator;
use Pharaonic\Laravel\ApiResponse\Tests\TestCase;

final class RequestIdTest extends TestCase
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    protected function setUp(): void
    {
        parent::setUp();

        $router = $this->app['router'];
        $router->get('api/id', fn () => Response::success('ok'))->middleware(AssignRequestId::class);
        $router->get('api/hidden', fn () => Response::success('ok')->withoutRequestId())->middleware(AssignRequestId::class);
        $router->get('api/raw', fn () => response('plain'))->middleware(AssignRequestId::class);
        $router->get('api/log', function () {
            Log::info('inside');

            return Response::success('ok');
        })->middleware(AssignRequestId::class);
    }

    public function test_an_id_is_generated_and_sent_in_the_header_and_body(): void
    {
        $response = $this->getJson('api/id');
        $id = $response->headers->get('X-Request-ID');

        $this->assertMatchesRegularExpression(self::UUID, (string) $id);
        $this->assertSame($id, $response->json('request_id'));
    }

    public function test_an_incoming_id_is_ignored_unless_trusted(): void
    {
        $response = $this->getJson('api/id', ['X-Request-ID' => 'client-id-1']);

        $this->assertNotSame('client-id-1', $response->headers->get('X-Request-ID'));
    }

    public function test_a_trusted_valid_incoming_id_is_reused(): void
    {
        $this->configure(['request_id.trust_incoming' => true]);

        $this->assertSame('client-id-1', $this->getJson('api/id', ['X-Request-ID' => 'client-id-1'])->headers->get('X-Request-ID'));
    }

    public function test_a_trusted_but_invalid_incoming_id_is_replaced(): void
    {
        $this->configure(['request_id.trust_incoming' => true, 'request_id.max_length' => 16]);

        foreach (["evil\r\nSet-Cookie: x=1", 'has space', str_repeat('a', 17), '', '<script>'] as $incoming) {
            $id = $this->getJson('api/id', ['X-Request-ID' => $incoming])->headers->get('X-Request-ID');

            $this->assertMatchesRegularExpression(self::UUID, (string) $id, $incoming);
        }
    }

    public function test_responses_outside_api_response_get_the_header(): void
    {
        $response = $this->get('api/raw');

        $this->assertSame('plain', $response->getContent());
        $this->assertMatchesRegularExpression(self::UUID, (string) $response->headers->get('X-Request-ID'));
    }

    public function test_the_body_can_leave_the_id_out(): void
    {
        $this->assertArrayNotHasKey('request_id', $this->getJson('api/hidden')->json());

        $this->configure(['request_id.include_in_body' => false]);
        $this->assertArrayNotHasKey('request_id', $this->getJson('api/id')->json());
    }

    public function test_with_request_id_overrides_the_default(): void
    {
        $this->configure(['request_id.include_in_body' => false]);
        $this->app['router']->get('api/shown', fn () => Response::success()->withRequestId())->middleware(AssignRequestId::class);

        $this->assertNotNull($this->getJson('api/shown')->json('request_id'));
    }

    public function test_without_the_middleware_the_field_is_left_out(): void
    {
        $this->assertSame(['success' => true], $this->body($this->finalize(Response::success()->withRequestId())));
    }

    public function test_the_header_name_and_generator_are_configurable(): void
    {
        $this->configure(['request_id.header' => 'X-Correlation-ID', 'request_id.generator' => SequenceGenerator::class]);

        $this->assertSame('req-1', $this->getJson('api/id')->headers->get('X-Correlation-ID'));
    }

    public function test_a_generator_returning_an_unsafe_id_is_an_error(): void
    {
        $this->configure(['request_id.generator' => UnsafeGenerator::class]);

        $this->withoutExceptionHandling();
        $this->expectException(\UnexpectedValueException::class);

        $this->getJson('api/id');
    }

    public function test_an_unknown_generator_is_refused(): void
    {
        $this->configure(['request_id.generator' => 'ordered_uuid']);

        $this->withoutExceptionHandling();
        $this->expectException(\InvalidArgumentException::class);

        $this->getJson('api/id');
    }

    public function test_each_request_gets_its_own_id(): void
    {
        $first = $this->getJson('api/id')->headers->get('X-Request-ID');
        $second = $this->getJson('api/id')->headers->get('X-Request-ID');

        $this->assertNotSame($first, $second);
    }

    public function test_log_records_carry_the_id_of_their_own_request_only(): void
    {
        $handler = new TestHandler;
        Log::driver()->getLogger()->pushHandler($handler);

        $first = $this->getJson('api/log')->headers->get('X-Request-ID');
        $second = $this->getJson('api/log')->headers->get('X-Request-ID');
        $this->app->instance('request', \Illuminate\Http\Request::create('/outside'));
        Log::info('outside');

        $records = $handler->getRecords();
        $ids = array_map(fn ($record) => $record['extra']['request_id'] ?? null, $records);

        $this->assertSame([$first, $second, null], $ids);
    }

    public function test_logging_can_be_turned_off(): void
    {
        $this->configure(['request_id.logging' => false]);
        $handler = new TestHandler;
        Log::driver()->getLogger()->pushHandler($handler);

        $this->getJson('api/log');

        $this->assertArrayNotHasKey('request_id', $handler->getRecords()[0]['extra']);
    }
}
