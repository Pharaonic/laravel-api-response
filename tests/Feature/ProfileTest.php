<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Feature;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Pharaonic\Laravel\ApiResponse\Http\Middleware\UseResponseProfile;
use Pharaonic\Laravel\ApiResponse\Profiles\Profile;
use Pharaonic\Laravel\ApiResponse\Response;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\MobileProfile;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\Post;
use Pharaonic\Laravel\ApiResponse\Tests\Fixtures\RawProfile;
use Pharaonic\Laravel\ApiResponse\Tests\TestCase;

final class ProfileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->configure([
            'profiles' => ['default' => Profile::class, 'mobile' => MobileProfile::class, 'raw' => RawProfile::class],
        ]);
    }

    public function test_a_custom_profile_shapes_the_whole_response(): void
    {
        $this->posts(3);
        $this->visit('/api/posts');

        $response = $this->finalize(Response::success(Post::query()->paginate(2))->profile('mobile')->notify('Loaded.', 'success')->header('X-API-Version', '1'));
        $body = $this->body($response);

        $this->assertTrue($body['ok']);
        $this->assertSame([1, 2], array_column($body['result'], 'id'));
        $this->assertSame(['type' => 'length_aware', 'has_more' => true], $body['meta']['pagination']);
        $this->assertSame([['text' => 'Loaded.', 'kind' => 'success']], $body['notifications']);
        $this->assertSame('mobile', $response->headers->get('X-Profile'));
        $this->assertSame('1', $response->headers->get('X-API-Version'));
    }

    public function test_a_profile_can_send_the_data_without_an_envelope(): void
    {
        $this->assertSame('[1,2]', $this->finalize(Response::success([1, 2])->profile('raw')->message('ignored'))->getContent());
    }

    public function test_the_explicit_profile_wins_over_the_route_and_negotiation(): void
    {
        $this->configure(['content_negotiation.enabled' => true, 'content_negotiation.media_types' => ['application/vnd.acme.raw+json' => 'raw']]);
        $this->app['router']->get('api/a', fn () => Response::success([1])->profile('default'))->middleware(UseResponseProfile::class.':mobile');
        $this->app['router']->get('api/b', fn () => Response::success([1]))->middleware(UseResponseProfile::class.':mobile');
        $this->app['router']->get('api/c', fn () => Response::success([1]));

        $accept = ['Accept' => 'application/vnd.acme.raw+json'];

        $this->get('api/a', $accept)->assertExactJson(['success' => true, 'data' => [1]]);
        $this->get('api/b', $accept)->assertExactJson(['ok' => true, 'result' => [1]]);
        $this->assertSame('[1]', $this->get('api/c', $accept)->getContent());
        $this->get('api/c')->assertExactJson(['success' => true, 'data' => [1]]);
    }

    public function test_negotiation_follows_quality_and_ignores_unknown_types(): void
    {
        $this->configure(['content_negotiation.enabled' => true, 'content_negotiation.media_types' => [
            'application/vnd.acme.mobile+json' => 'mobile',
            'application/vnd.acme.raw+json' => 'raw',
        ]]);

        $response = $this->finalize(Response::success([1]), '/api/x', 'GET', ['HTTP_ACCEPT' => 'application/vnd.acme.raw+json;q=0.4, application/vnd.acme.mobile+json;q=0.9, */*;q=0.1']);
        $this->assertSame(['ok' => true, 'result' => [1]], $this->body($response));
        $this->assertSame('application/vnd.acme.mobile+json', $response->headers->get('Content-Type'));
        $this->assertSame('Accept', $response->headers->get('Vary'));

        $refused = $this->finalize(Response::success([1]), '/api/x', 'GET', ['HTTP_ACCEPT' => 'application/vnd.acme.mobile+json;q=0, application/json']);
        $this->assertSame(['success' => true, 'data' => [1]], $this->body($refused));
        $this->assertSame('application/json', $refused->headers->get('Content-Type'));

        $profileName = $this->finalize(Response::success([1]), '/api/x', 'GET', ['HTTP_ACCEPT' => 'mobile']);
        $this->assertSame(['success' => true, 'data' => [1]], $this->body($profileName));
    }

    public function test_negotiation_adds_accept_to_the_vary_the_response_sets(): void
    {
        $this->configure(['content_negotiation.enabled' => true, 'content_negotiation.media_types' => ['application/vnd.acme.mobile+json' => 'mobile']]);

        $this->assertSame(['Origin', 'Accept'], $this->finalize(Response::success([1])->header('Vary', 'Origin'))->getVary());
        $this->assertSame(['Origin', 'Accept'], $this->finalize(Response::success([1])->header('vary', 'Origin'))->getVary());
        $this->assertSame(['accept', 'Origin'], $this->finalize(Response::success([1])->header('Vary', 'accept, Origin'))->getVary());
        $this->assertSame(['*'], $this->finalize(Response::success([1])->header('Vary', '*'))->getVary());
        $this->assertSame(['Accept'], $this->finalize(Response::noContent())->getVary());
    }

    public function test_negotiation_is_off_by_default(): void
    {
        $this->configure(['content_negotiation.media_types' => ['application/vnd.acme.mobile+json' => 'mobile']]);

        $response = $this->finalize(Response::success([1]), '/api/x', 'GET', ['HTTP_ACCEPT' => 'application/vnd.acme.mobile+json']);

        $this->assertSame(['success' => true, 'data' => [1]], $this->body($response));
        $this->assertFalse($response->headers->has('Vary'));
    }

    public function test_the_configured_default_profile_is_used(): void
    {
        $this->configure(['default_profile' => 'mobile']);

        $this->assertSame(['ok' => true], $this->body($this->finalize(Response::success())));
    }

    public function test_an_unknown_profile_is_a_clear_error(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[tablet] is not registered');

        $this->finalize(Response::success()->profile('tablet'));
    }

    public function test_the_middleware_refuses_an_unknown_profile(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new UseResponseProfile($this->app['config']))->handle(Request::create('/'), fn () => null, 'tablet');
    }

    public function test_envelope_fields_can_be_renamed_in_the_configuration(): void
    {
        $this->configure(['envelope.fields.data' => 'payload', 'envelope.fields.success' => 'status']);

        $this->assertSame(['status' => true, 'payload' => 1], $this->body($this->finalize(Response::success(1))));
    }

    public function test_colliding_field_names_are_refused(): void
    {
        $this->configure(['envelope.fields.data' => 'message']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('two fields under the name [message]');

        $this->finalize(Response::success(1));
    }

    public function test_a_profile_class_must_extend_profile(): void
    {
        $this->configure(['profiles.bad' => \stdClass::class]);

        $this->expectException(InvalidArgumentException::class);

        $this->finalize(Response::success()->profile('bad'));
    }
}
