<?php

namespace Notifications\Events\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Notifications\Dtos\NotificationCreateDto;
use Notifications\Enums\NotificationSeverityEnum;
use Notifications\Services\Interfaces\NotificationServiceInterface;
use Objects\Events\CameraDeletedEvent;

/**
 * Fan-out listener for camera removal — see BroadcastObjectDeletedListener for the rationale
 * (durable bell entry alongside the `cameras.deleted` broadcast the event already emits).
 */
class BroadcastCameraDeletedListener implements ShouldQueue
{
    // Bez $queue celowo — patrz GenerateThumbnailListener.

    public function __construct(
        protected NotificationServiceInterface $notifications,
    ) {
    }

    public function handle(CameraDeletedEvent $event): void
    {
        $camera = $event->camera;
        $companyId = (int) ($camera->company_id ?? 0);
        if ($companyId === 0) {
            return;
        }

        $cameraName = $camera->name ?? '—';

        $userIds = DB::table('sec_users')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        foreach ($userIds as $userId) {
            $this->notifications->create(new NotificationCreateDto(
                companyId: $companyId,
                userId: (int) $userId,
                type: 'camera_deleted',
                severity: NotificationSeverityEnum::Warning,
                title: 'Camera deleted',
                message: "Camera {$cameraName} has been deleted.",
                link: null,
                data: [
                    'camera_name' => $cameraName,
                ],
            ));
        }
    }
}
