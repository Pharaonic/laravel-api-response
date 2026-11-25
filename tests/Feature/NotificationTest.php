<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Feature;

use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Pharaonic\Laravel\ApiResponse\Response;
use Pharaonic\Laravel\ApiResponse\Tests\TestCase;

final class NotificationTest extends TestCase
{
    public function test_no_notifications_means_no_field(): void
    {
        $this->assertArrayNotHasKey('notifications', $this->body($this->finalize(Response::success())));
    }

    public function test_notifications_keep_their_order_and_optional_fields(): void
    {
        $body = $this->body($this->finalize(
            Response::created(['id' => 1])
                ->notify('Post created.', 'success')
                ->notify('Waiting for review.')
                ->notify(message: 'Done.', type: 'warning', title: 'Done', duration: 5000, data: ['post_id' => 1])
        ));

        $this->assertSame([
            ['type' => 'success', 'message' => 'Post created.'],
            ['type' => 'info', 'message' => 'Waiting for review.'],
            ['type' => 'warning', 'message' => 'Done.', 'title' => 'Done', 'duration' => 5000, 'data' => ['post_id' => 1]],
        ], $body['notifications']);
    }

    public function test_errors_can_carry_notifications(): void
    {
        $body = $this->body($this->finalize(Response::error('Failed.', 409)->notify('Try again.', 'error')));

        $this->assertSame([['type' => 'error', 'message' => 'Try again.']], $body['notifications']);
    }

    public function test_custom_types_can_be_registered(): void
    {
        $this->configure(['notifications.types' => ['info', 'celebrate']]);

        $this->assertSame('celebrate', $this->body($this->finalize(Response::success()->notify('Yay', 'celebrate')))['notifications'][0]['type']);
    }

    public function test_an_unknown_type_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[party]');

        $this->finalize(Response::success()->notify('x', 'party'));
    }

    public function test_a_negative_duration_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Response::success()->notify('x', 'info', null, -1);
    }

    public function test_the_default_type_must_be_registered(): void
    {
        $this->configure(['notifications.default_type' => 'loud']);

        $this->expectException(InvalidArgumentException::class);

        $this->finalize(Response::success()->notify('x'));
    }

    public function test_notify_never_dispatches_a_laravel_notification(): void
    {
        Notification::fake();

        $this->finalize(Response::success()->notify('Only in the body.'));

        Notification::assertNothingSent();
    }
}
