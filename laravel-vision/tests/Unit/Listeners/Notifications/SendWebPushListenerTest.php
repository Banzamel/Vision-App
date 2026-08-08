<?php

namespace Tests\Unit\Listeners\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use Mockery;
use Notifications\Enums\NotificationSeverityEnum;
use Notifications\Events\Listeners\SendWebPushListener;
use Notifications\Events\NotificationCreatedEvent;
use Notifications\Models\Notification;
use Push\Services\Interfaces\WebPushSenderInterface;
use Tests\TestCase;

class SendWebPushListenerTest extends TestCase
{
    public function test_implements_should_queue(): void
    {
        $sender = Mockery::mock(WebPushSenderInterface::class);
        $listener = new SendWebPushListener($sender);

        $this->assertInstanceOf(ShouldQueue::class, $listener);
        // Patrz GenerateThumbnailListenerTest — kolejka pochodzi z REDIS_QUEUE, nie z listenera.
        $this->assertFalse(property_exists($listener, 'queue'));
    }

    public function test_handle_forwards_notification_payload_to_sender(): void
    {
        $sender = Mockery::mock(WebPushSenderInterface::class);

        $notification = new Notification();
        $notification->id = 7;
        $notification->user_id = 42;
        $notification->title = 'Nowy album';
        $notification->message = 'Zsynchronizowano nowy album';
        $notification->link = '/objects?album=99';
        $notification->severity = NotificationSeverityEnum::Info->value;
        $notification->type = 'album_created';

        $sender->shouldReceive('sendToUser')->once()->with(42, Mockery::on(function (array $payload) {
            return $payload['id'] === '7'
                && $payload['title'] === 'Nowy album'
                && $payload['message'] === 'Zsynchronizowano nowy album'
                && $payload['link'] === '/objects?album=99'
                && $payload['type'] === 'album_created';
        }));

        $listener = new SendWebPushListener($sender);
        $listener->handle(new NotificationCreatedEvent($notification));

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
