<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Pharaonic\Laravel\ApiResponse\Http\Middleware\AssignRequestId;
use Pharaonic\Laravel\ApiResponse\Response;
use Pharaonic\Laravel\ApiResponse\Support\RequestId;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\ApiExceptionHandler;
use Pharaonic\Laravel\ApiResponse\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class RequestIdTest extends TestCase
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('api/posts', fn () => Response::success(['id' => 1]))->middleware(AssignRequestId::class);
        $this->app['router']->get('api/plain', fn () => 'plain')->middleware(AssignRequestId::class);
    }

    public function test_the_id_is_sent_in_the_header_and_the_body(): void
    {
        $response = $this->getJson('api/posts');
        $id = $response->headers->get('X-Request-ID');

        $this->assertIsString($id);
        $this->assertMatchesRegularExpression(self::UUID, $id);
        $response->assertJson(['request_id' => $id]);
    }

    public function test_every_request_gets_its_own_id(): void
    {
        $first = $this->getJson('api/posts')->headers->get('X-Request-ID');
        $second = $this->getJson('api/posts')->headers->get('X-Request-ID');

        $this->assertNotSame($first, $second);
    }

    public function test_an_incoming_id_is_ignored_by_default(): void
    {
        $response = $this->getJson('api/posts', ['X-Request-ID' => 'client-id']);

        $this->assertNotSame('client-id', $response->headers->get('X-Request-ID'));
    }

    public function test_a_valid_incoming_id_is_reused_when_trusted(): void
    {
        $this->configure(['request_id.trust_incoming' => true]);

        $this->getJson('api/posts', ['X-Request-ID' => 'client-id.1'])
            ->assertHeader('X-Request-ID', 'client-id.1')
            ->assertJson(['request_id' => 'client-id.1']);
    }

    public function test_an_invalid_incoming_id_is_replaced_even_when_trusted(): void
    {
        $this->configure(['request_id.trust_incoming' => true]);

        foreach (['bad id', str_repeat('a', 129), "evil\nheader", '<script>'] as $incoming) {
            $id = $this->getJson('api/posts', ['X-Request-ID' => $incoming])->headers->get('X-Request-ID');

            $this->assertIsString($id);
            $this->assertMatchesRegularExpression(self::UUID, $id);
        }
    }

    public function test_the_header_name_is_configurable(): void
    {
        $this->configure(['request_id.header' => 'X-Trace-ID']);

        $response = $this->getJson('api/posts');

        $this->assertNull($response->headers->get('X-Request-ID'));
        $this->assertIsString($response->headers->get('X-Trace-ID'));
    }

    public function test_any_response_gets_the_header(): void
    {
        $this->assertIsString($this->get('api/plain')->headers->get('X-Request-ID'));
    }

    public function test_a_rendered_error_carries_the_id(): void
    {
        $this->app->instance(ExceptionHandler::class, new ApiExceptionHandler($this->app));
        $this->app['router']->get('api/fail', function (): void {
            throw new HttpException(409, 'Conflict.');
        })->middleware(AssignRequestId::class);

        $response = $this->getJson('api/fail');
        $id = $response->headers->get('X-Request-ID');

        $this->assertIsString($id);
        $response->assertStatus(409)->assertJson(['success' => false, 'request_id' => $id]);
    }

    public function test_a_response_without_the_middleware_has_no_id(): void
    {
        $this->assertArrayNotHasKey('request_id', $this->body($this->build(Response::success())));
    }

    public function test_log_records_carry_the_id_of_their_own_request(): void
    {
        $handler = new TestHandler;
        $monolog = Log::driver()->getLogger();
        $this->assertInstanceOf(\Monolog\Logger::class, $monolog);
        $monolog->pushHandler($handler);

        $this->app['router']->get('api/log', function () {
            Log::info('inside');

            return 'ok';
        })->middleware(AssignRequestId::class);

        $first = $this->get('api/log')->headers->get('X-Request-ID');
        $second = $this->get('api/log')->headers->get('X-Request-ID');

        $records = $handler->getRecords();
        $this->assertCount(2, $records);
        $this->assertSame($first, $records[0]['extra']['request_id']);
        $this->assertSame($second, $records[1]['extra']['request_id']);

        $processors = array_filter($monolog->getProcessors(), fn ($processor) => $processor instanceof RequestId);
        $this->assertCount(1, $processors);
    }

    public function test_logging_can_be_turned_off(): void
    {
        $this->configure(['request_id.logging' => false]);
        $monolog = Log::driver()->getLogger();
        $this->assertInstanceOf(\Monolog\Logger::class, $monolog);

        $this->get('api/plain');

        $this->assertSame([], array_filter($monolog->getProcessors(), fn ($processor) => $processor instanceof RequestId));
    }
}
