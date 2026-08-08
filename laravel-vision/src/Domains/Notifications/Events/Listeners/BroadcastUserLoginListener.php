<?php

namespace Notifications\Events\Listeners;

use Auth\Events\LoginEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Notifications\Dtos\NotificationCreateDto;
use Notifications\Enums\NotificationSeverityEnum;
use Notifications\Services\Interfaces\NotificationServiceInterface;

/**
 * When a user logs in, send a "logged in" notification to every OTHER member of the same
 * company. The actor sees their own login in /users activity, so we skip them here.
 */
class BroadcastUserLoginListener implements ShouldQueue
{
    // Bez $queue celowo — patrz GenerateThumbnailListener.

    public function __construct(
        protected NotificationServiceInterface $notifications,
    ) {
    }

    public function handle(LoginEvent $event): void
    {
        $actor = $event->user;
        $companyId = (int) ($actor->company_id ?? 0);
        if ($companyId === 0) {
            return;
        }

        $userIds = DB::table('sec_users')
            ->where('company_id', $companyId)
            ->where('id', '!=', $actor->id)
            ->pluck('id')
            ->all();

        foreach ($userIds as $userId) {
            $this->notifications->create(new NotificationCreateDto(
                companyId: $companyId,
                userId: (int) $userId,
                type: 'user_login',
                severity: NotificationSeverityEnum::Info,
                // EN fallback used by Web Push (OS notification) and when the frontend doesn't
                // recognise the `type`. Frontend bell-icon renders from `data` via i18n.
                title: 'User signed in',
                message: "{$actor->name} signed in.",
                link: null,
                data: [
                    'actor_name' => $actor->name,
                ],
            ));
        }
    }
}
