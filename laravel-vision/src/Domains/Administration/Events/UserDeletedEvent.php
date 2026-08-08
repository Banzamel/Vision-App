<?php

namespace Administration\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched after a user has been removed from the company.
 * Holds a snapshot of the data because the model may no longer exist when the event is broadcast.
 */
class UserDeletedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private int $userId;
    private string $userName;
    private string $userEmail;
    private int $companyId;

    /**
     * Stores a snapshot of the deleted user's data and information about the actor.
     *
     * @param \Administration\Models\User $deletedUser user that has been deleted
     * @param \Auth\Models\User $user actor that performed the operation (authenticated user)
     */
    public function __construct(
        \Administration\Models\User $deletedUser,
        public readonly \Auth\Models\User $user
    ) {
        $this->userId = $deletedUser->id;
        $this->userName = $deletedUser->name;
        $this->userEmail = $deletedUser->email;
        $this->companyId = $deletedUser->company_id;
    }

    /**
     * Returns the tenant the deleted user belonged to.
     *
     * @return int company id
     */
    public function getCompanyId(): int
    {
        return $this->companyId;
    }

    /**
     * Returns the id of the deleted user.
     *
     * @return int user id
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Returns the name of the deleted user (snapshot taken before removal).
     *
     * @return string user name
     */
    public function getUserName(): string
    {
        return $this->userName;
    }

    /**
     * Returns the list of channels the event should be broadcast on (company presence channel).
     *
     * @return array<int, PresenceChannel> list of broadcast channels
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel(config('broadcasting.channel') . '.company.' . $this->companyId),
        ];
    }

    /**
     * Returns the event name visible on the client side (frontend).
     *
     * @return string broadcast event name
     */
    public function broadcastAs(): string
    {
        return 'administration.user';
    }

    /**
     * Returns the data attached to the broadcast event (who, target, action, when).
     *
     * @return array<string, mixed> event payload
     */
    public function broadcastWith(): array
    {
        return [
            'target_user' => ['id' => $this->userId, 'name' => $this->userName, 'email' => $this->userEmail],
            'user' => ['name' => $this->user->name],
            'action' => 'deleted',
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
