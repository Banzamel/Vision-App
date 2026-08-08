<?php

namespace App\Providers;

/**
 * Event provider — maps domain events to their listeners.
 */
class EventServiceProvider extends \Illuminate\Foundation\Support\Providers\EventServiceProvider
{
    /**
     * @var array<class-string, array<class-string>> Map: event class -> list of listener classes.
     */
    protected $listen = [
        // Auth
        \Auth\Events\LoginEvent::class => [
            \Auth\Events\Listeners\LoginListener::class,
            \Notifications\Events\Listeners\BroadcastUserLoginListener::class,
        ],
        \Auth\Events\LogoutEvent::class => [
            \Auth\Events\Listeners\LogoutListener::class
        ],

        // Administration
        \Administration\Events\UserCreatedEvent::class => [],
        \Administration\Events\UserUpdatedEvent::class => [],
        \Administration\Events\UserDeletedEvent::class => [
            \Notifications\Events\Listeners\BroadcastUserDeletedListener::class,
        ],
        \Administration\Events\UserAvatarUpdatedEvent::class => [],
        \Administration\Events\RoleCreatedEvent::class => [],
        \Administration\Events\RoleUpdatedEvent::class => [],
        \Administration\Events\RoleDeletedEvent::class => [],

        // Objects — pozostałe eventy z tej domeny nie mają wpisu, bo implementują ShouldBroadcast
        // i Laravel rozgłasza je bez listenera. Tu są tylko te, z których robimy wpis w dzwonku.
        \Objects\Events\ObjectDeletedEvent::class => [
            \Notifications\Events\Listeners\BroadcastObjectDeletedListener::class,
        ],
        \Objects\Events\CameraDeletedEvent::class => [
            \Notifications\Events\Listeners\BroadcastCameraDeletedListener::class,
        ],

        // FileManager
        \FileManager\Events\FileUploadEvent::class => [],

        // Albums
        \Albums\Events\PhotoAddedEvent::class => [
            \Albums\Events\Listeners\GenerateThumbnailListener::class,
        ],
        \Albums\Events\AlbumCreatedEvent::class => [
            \Notifications\Events\Listeners\BroadcastAlbumCreatedListener::class,
        ],

        // Notifications — every freshly created Notification fans out to Web Push subscriptions.
        \Notifications\Events\NotificationCreatedEvent::class => [
            \Notifications\Events\Listeners\SendWebPushListener::class,
        ],
    ];
}
