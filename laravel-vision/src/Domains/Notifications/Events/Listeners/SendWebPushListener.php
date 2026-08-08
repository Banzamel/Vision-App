<?php

namespace Notifications\Events\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Notifications\Events\NotificationCreatedEvent;
use Push\Services\Interfaces\WebPushSenderInterface;

/**
 * Listener that fans a freshly-created Notification out to the user's registered Web Push
 * subscriptions. Runs on the queue so the HTTP request that triggered the Notification (album
 * sync, login event, etc.) does not block on the HTTP fan-out to the browser push services.
 */
class SendWebPushListener implements ShouldQueue
{
    // Bez $queue celowo — patrz GenerateThumbnailListener. Nazwę kolejki bierzemy
    // z konfiguracji połączenia (REDIS_QUEUE), nie zaszywamy jej w listenerze.

    /**
     * @param WebPushSenderInterface $sender Adapter around minishlink/web-push.
     */
    public function __construct(
        protected WebPushSenderInterface $sender,
    ) {
    }

    /**
     * Forward title / message / link to the user's push subscriptions. The service worker on the
     * frontend picks up this payload in the "push" event and renders an OS notification.
     *
     * @param NotificationCreatedEvent $event Triggered from NotificationService::create().
     */
    public function handle(NotificationCreatedEvent $event): void
    {
        $notification = $event->notification;

        $payload = [
            'id' => (string) $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'link' => $notification->link,
            'severity' => $notification->severity,
            'type' => $notification->type,
        ];

        $this->sender->sendToUser((int) $notification->user_id, $payload);
    }
}
