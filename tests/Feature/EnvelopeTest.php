<?php

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Http\Request;
use Pharaonic\Laravel\ApiResponse\Response;

test('a success sends success message and data', function () {
    $response = $this->build(Response::success(['id' => 1]));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('application/json', $response->headers->get('Content-Type'));
    $this->assertSame(['success' => true, 'message' => null, 'data' => ['id' => 1], 'meta' => null], $this->body($response));
});

test('an error sends success message and errors', function () {
    $response = $this->build(Response::error('Post not found.', 404));

    $this->assertSame(404, $response->getStatusCode());
    $this->assertSame(['success' => false, 'message' => 'Post not found.', 'errors' => null, 'meta' => null], $this->body($response));
});

test('an error defaults to 400', function () {
    $this->assertSame(400, $this->build(Response::error('Bad.'))->getStatusCode());
});

test('validation errors are sent per field', function () {
    $response = $this->build(
        Response::error('Validation failed.', 422)->errors(['email' => ['The email field is required.']])
    );

    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame(['email' => ['The email field is required.']], $this->body($response)['errors']);
});

test('data set to null an empty array false or zero is kept', function () {
    foreach ([null, [], false, 0, ''] as $value) {
        $body = $this->body($this->build(Response::success($value)));

        $this->assertArrayHasKey('data', $body);
        $this->assertSame($value, $body['data']);
    }
});

test('modifiers can be called in any order', function () {
    $first = Response::success(['id' => 1])->notify('Created.', 'success')->status(201)->message('Post created.');
    $second = Response::success(['id' => 1])->message('Post created.')->status(201)->notify('Created.', 'success');

    $this->assertSame($this->build($first)->getContent(), $this->build($second)->getContent());
    $this->assertSame(201, $this->build($first)->getStatusCode());
});

test('a builder can be built twice', function () {
    $response = Response::success(['id' => 1])->notify('Once.');

    $this->assertSame($this->build($response)->getContent(), $this->build($response)->getContent());
});

test('it is responsable from a route', function () {
    $this->app['router']->post('api/posts', fn () => Response::success(['id' => 1])->status(201)->message('Post created.'));

    $this->postJson('api/posts')
        ->assertStatus(201)
        ->assertExactJson(['success' => true, 'message' => 'Post created.', 'data' => ['id' => 1], 'meta' => null]);
});

test('response builds for the current request', function () {
    $this->app->instance('request', Request::create('/api/posts'));

    $response = Response::success(['id' => 1])->response();

    $this->assertSame(['success' => true, 'message' => null, 'data' => ['id' => 1], 'meta' => null], $this->body($response));
});

test('204 and 205 have no body', function () {
    foreach ([204, 205] as $status) {
        $response = $this->build(Response::success(['ignored' => true])->status($status)->notify('Hidden.'));

        $this->assertSame($status, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }
});

test('a success refuses a status outside 2xx', function () {
    foreach ([199, 301, 400, 500] as $status) {
        try {
            Response::success()->status($status);
            $this->fail("Status {$status} was accepted for a success.");
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString((string) $status, $exception->getMessage());
        }
    }
});

test('an error refuses a status outside 4xx and 5xx', function () {
    foreach ([200, 204, 399, 600] as $status) {
        try {
            Response::error('Nope.', $status);
            $this->fail("Status {$status} was accepted for an error.");
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString((string) $status, $exception->getMessage());
        }
    }
});

test('a success refuses errors', function () {
    $this->expectException(InvalidArgumentException::class);

    Response::success()->errors(['field' => ['Nope.']]);
});

test('arrayable and jsonable data are serialized', function () {
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
});

test('an ordinary laravel response is left alone', function () {
    $this->app['router']->get('api/plain', fn () => ['plain' => true]);
    $this->app['router']->get('page', fn () => 'Hello');

    $this->getJson('api/plain')->assertExactJson(['plain' => true]);
    $this->get('page')->assertSee('Hello');
});

test('notifications are sent in order', function () {
    $body = $this->body($this->build(
        Response::success()->notify('Saved.', 'success')->notify('Check your email.')->notify('Quota low.', 'warning')
    ));

    $this->assertSame([
        ['type' => 'success', 'message' => 'Saved.'],
        ['type' => 'info', 'message' => 'Check your email.'],
        ['type' => 'warning', 'message' => 'Quota low.'],
    ], $body['notifications']);
});

test('an error can notify', function () {
    $body = $this->body($this->build(Response::error('Failed.')->notify('Try again.', 'error')));

    $this->assertSame([['type' => 'error', 'message' => 'Try again.']], $body['notifications']);
});

test('an unknown notification type is refused', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('[danger]');

    Response::success()->notify('Nope.', 'danger');
});

test('a notification sends its optional parts in a fixed order', function () {
    $body = $this->body($this->build(Response::success()->notify(
        'Your post is live.',
        'success',
        position: 'top-right',
        duration: 5000,
        action: ['url' => '/posts/1', 'label' => 'View'],
        code: 'post.published',
        title: 'Published'
    )));

    $this->assertSame([[
        'type' => 'success',
        'title' => 'Published',
        'message' => 'Your post is live.',
        'code' => 'post.published',
        'action' => ['label' => 'View', 'url' => '/posts/1'],
        'duration' => 5000,
        'position' => 'top-right',
    ]], $body['notifications']);
});

test('a notification leaves out the parts it was not given', function () {
    $body = $this->body($this->build(Response::success()->notify('Saved.', title: 'Done')->notify('Kept.', duration: 0)));

    $this->assertSame([
        ['type' => 'info', 'title' => 'Done', 'message' => 'Saved.'],
        ['type' => 'info', 'message' => 'Kept.', 'duration' => 0],
    ], $body['notifications']);
});

test('an invalid notification action is refused', function (array $action) {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('label and url');

    Response::success()->notify('Nope.', action: $action);
})->with([
    'no url' => [['label' => 'View']],
    'no label' => [['url' => '/posts']],
    'empty label' => [['label' => '', 'url' => '/posts']],
    'non-string url' => [['label' => 'View', 'url' => 1]],
    'extra key' => [['label' => 'View', 'url' => '/posts', 'method' => 'POST']],
    'list' => [['View', '/posts']],
]);

test('a negative notification duration is refused', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('-1 given');

    Response::success()->notify('Nope.', duration: -1);
});

test('an unknown notification position is refused', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('[middle]');

    Response::success()->notify('Nope.', position: 'middle');
});

test('optional fields are left out when unused', function () {
    $this->assertSame(['success', 'message', 'data', 'meta'], array_keys($this->body($this->build(Response::success()))));
    $this->assertSame(['success', 'message', 'errors', 'meta'], array_keys($this->body($this->build(Response::error('Nope.')))));
});

test('meta is null until something is added', function () {
    $this->assertNull($this->body($this->build(Response::success()))['meta']);
    $this->assertNull($this->body($this->build(Response::error('Nope.')))['meta']);
});

test('meta adds one key or many', function () {
    $body = $this->body($this->build(
        Response::success()->meta('version', '1.0')->meta(['count' => 3, 'version' => '2.0'])->meta('flags', [])
    ));

    $this->assertSame(['version' => '2.0', 'count' => 3, 'flags' => []], $body['meta']);
});

test('an error can carry meta', function () {
    $body = $this->body($this->build(Response::error('Insufficient balance.', 422)->meta('balance', 10)));

    $this->assertSame(['balance' => 10], $body['meta']);
});

test('the pagination meta key is reserved', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('[pagination]');

    Response::success()->meta('pagination', []);
});
