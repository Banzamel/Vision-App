<?php

namespace Notifications\Events\Listeners;

use Administration\Events\UserDeletedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Notifications\Dtos\NotificationCreateDto;
use Notifications\Enums\NotificationSeverityEnum;
use Notifications\Services\Interfaces\NotificationServiceInterface;

/**
 * Fan-out listener: when a user is removed from the company, notify the remaining members.
 *
 * Two people are skipped — the actor (who just performed the deletion and already saw the
 * result) and the deleted user themselves. The latter matters in practice: sec_users is
 * soft-deleted, so the row is still there and a plain company query would happily write a
 * "you were deleted" notification into the inbox of an account that no longer exists.
 */
class BroadcastUserDeletedListener implements ShouldQueue
{
    // Bez $queue celowo — patrz GenerateThumbnailListener.

    public function __construct(
        protected NotificationServiceInterface $notifications,
    ) {
    }

    public function handle(UserDeletedEvent $event): void
    {
        $companyId = $event->getCompanyId();
        if ($companyId === 0) {
            return;
        }

        $deletedId = $event->getUserId();
        $deletedName = $event->getUserName();
        $actorName = $event->user->name;
        $actorId = (int) ($event->user->id ?? 0);

        $userIds = DB::table('sec_users')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereNotIn('id', array_filter([$deletedId, $actorId]))
            ->pluck('id')
            ->all();

        foreach ($userIds as $userId) {
            $this->notifications->create(new NotificationCreateDto(
                companyId: $companyId,
                userId: (int) $userId,
                type: 'user_deleted',
                severity: NotificationSeverityEnum::Warning,
                title: 'User deleted',
                message: "{$actorName} deleted user {$deletedName}.",
                link: '/users',
                data: [
                    'actor_name' => $actorName,
                    'user_name' => $deletedName,
                ],
            ));
        }
    }
}
