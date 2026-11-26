<?php

use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Pharaonic\Laravel\ApiResponse\Http\Middleware\AssignRequestId;
use Pharaonic\Laravel\ApiResponse\Response;
use Pharaonic\Laravel\ApiResponse\Support\RequestId;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->app['router']->get('api/posts', fn () => Response::success(['id' => 1]))->middleware(AssignRequestId::class);
    $this->app['router']->get('api/plain', fn () => 'plain')->middleware(AssignRequestId::class);
});

test('the id is sent in the header and the body', function () {
    $response = $this->getJson('api/posts');
    $id = $response->headers->get('X-Request-ID');

    $this->assertIsString($id);
    $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id);
    $response->assertJson(['request_id' => $id]);
});

test('every request gets its own id', function () {
    $first = $this->getJson('api/posts')->headers->get('X-Request-ID');
    $second = $this->getJson('api/posts')->headers->get('X-Request-ID');

    $this->assertNotSame($first, $second);
});

test('an incoming id is ignored by default', function () {
    $response = $this->getJson('api/posts', ['X-Request-ID' => 'client-id']);

    $this->assertNotSame('client-id', $response->headers->get('X-Request-ID'));
});

test('a valid incoming id is reused when trusted', function () {
    $this->configure(['request_id.trust_incoming' => true]);

    $this->getJson('api/posts', ['X-Request-ID' => 'client-id.1'])
        ->assertHeader('X-Request-ID', 'client-id.1')
        ->assertJson(['request_id' => 'client-id.1']);
});

test('an invalid incoming id is replaced even when trusted', function () {
    $this->configure(['request_id.trust_incoming' => true]);

    foreach (['bad id', str_repeat('a', 129), "evil\nheader", '<script>'] as $incoming) {
        $id = $this->getJson('api/posts', ['X-Request-ID' => $incoming])->headers->get('X-Request-ID');

        $this->assertIsString($id);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id);
    }
});

test('the header name is configurable', function () {
    $this->configure(['request_id.header' => 'X-Trace-ID']);

    $response = $this->getJson('api/posts');

    $this->assertNull($response->headers->get('X-Request-ID'));
    $this->assertIsString($response->headers->get('X-Trace-ID'));
});

test('any response gets the header', function () {
    $this->assertIsString($this->get('api/plain')->headers->get('X-Request-ID'));
});

test('a rendered error carries the id', function () {
    $this->useApiExceptionHandler();
    $this->app['router']->get('api/fail', function (): void {
        throw new HttpException(409, 'Conflict.');
    })->middleware(AssignRequestId::class);

    $response = $this->getJson('api/fail');
    $id = $response->headers->get('X-Request-ID');

    $this->assertIsString($id);
    $response->assertStatus(409)->assertJson(['success' => false, 'request_id' => $id]);
});

test('a response without the middleware has no id', function () {
    $this->assertArrayNotHasKey('request_id', $this->body($this->build(Response::success())));
});

test('log records carry the id of their own request', function () {
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
});

test('logging can be turned off', function () {
    $this->configure(['request_id.logging' => false]);
    $monolog = Log::driver()->getLogger();
    $this->assertInstanceOf(\Monolog\Logger::class, $monolog);

    $this->get('api/plain');

    $this->assertSame([], array_filter($monolog->getProcessors(), fn ($processor) => $processor instanceof RequestId));
});
