<?php

namespace Notifications\Events\Listeners;

use Albums\Events\AlbumCreatedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Notifications\Dtos\NotificationCreateDto;
use Notifications\Enums\NotificationSeverityEnum;
use Notifications\Services\Interfaces\NotificationServiceInterface;

/**
 * Fan-out listener: when AlbumSyncService creates a new daily album, drop a notification row
 * into every company user's inbox. The NotificationService dispatches NotificationCreatedEvent
 * per row, which Reverb pushes onto each user's private channel for live UI updates.
 */
class BroadcastAlbumCreatedListener implements ShouldQueue
{
    // Bez $queue celowo — patrz GenerateThumbnailListener.

    public function __construct(
        protected NotificationServiceInterface $notifications,
    ) {
    }

    public function handle(AlbumCreatedEvent $event): void
    {
        $album = $event->album;
        $companyId = (int) $album->company_id;
        $cameraName = $album->camera?->name ?? '—';
        $date = optional($album->date)->format('Y-m-d') ?? '—';

        $userIds = DB::table('sec_users')
            ->where('company_id', $companyId)
            ->pluck('id')
            ->all();

        foreach ($userIds as $userId) {
            $this->notifications->create(new NotificationCreateDto(
                companyId: $companyId,
                userId: (int) $userId,
                type: 'album_created',
                severity: NotificationSeverityEnum::Info,
                // EN fallback used by Web Push (OS notification) and when frontend doesn't
                // recognise the `type`. Frontend bell-icon renders from `data` via i18n.
                title: 'New album',
                message: "Album {$date} from camera {$cameraName} has been created.",
                link: '/objects?album=' . $album->id,
                data: [
                    'date' => $date,
                    'camera_name' => $cameraName,
                    'album_id' => (int) $album->id,
                ],
            ));
        }
    }
}
