<?php

namespace Notifications\Events\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Notifications\Dtos\NotificationCreateDto;
use Notifications\Enums\NotificationSeverityEnum;
use Notifications\Services\Interfaces\NotificationServiceInterface;
use Objects\Events\ObjectDeletedEvent;

/**
 * Fan-out listener: when an object is removed from the Vision tree, drop a notification row
 * into every company user's inbox. The event itself already broadcasts `objects.deleted` on the
 * company channel (the Dashboard reloads off it) — this adds the durable bell-icon entry.
 *
 * Sent to everyone, including whoever performed the deletion: the event carries no actor
 * (and the queued listener has no auth context to infer one from), so there is nobody to skip.
 */
class BroadcastObjectDeletedListener implements ShouldQueue
{
    // Bez $queue celowo — patrz GenerateThumbnailListener.

    public function __construct(
        protected NotificationServiceInterface $notifications,
    ) {
    }

    public function handle(ObjectDeletedEvent $event): void
    {
        $object = $event->object;
        $companyId = (int) ($object->company_id ?? 0);
        if ($companyId === 0) {
            return;
        }

        $objectName = $object->name ?? '—';

        // whereNull('deleted_at') — sec_users jest soft-delete'owane, a query builder (w odróżnieniu
        // od Eloquenta) nie zna global scope'ów, więc bez tego pisalibyśmy do skrzynek usuniętych kont.
        $userIds = DB::table('sec_users')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        foreach ($userIds as $userId) {
            $this->notifications->create(new NotificationCreateDto(
                companyId: $companyId,
                userId: (int) $userId,
                type: 'object_deleted',
                severity: NotificationSeverityEnum::Warning,
                // EN fallback used by Web Push (OS notification) and when the frontend doesn't
                // recognise the `type`. Frontend bell-icon renders from `data` via i18n.
                title: 'Object deleted',
                message: "Object {$objectName} has been deleted.",
                // Bez linku — celu już nie ma, klik prowadziłby na 404.
                link: null,
                data: [
                    'object_name' => $objectName,
                ],
            ));
        }
    }
}
