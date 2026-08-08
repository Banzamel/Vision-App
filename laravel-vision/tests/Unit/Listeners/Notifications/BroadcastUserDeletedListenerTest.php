<?php

namespace Tests\Unit\Listeners\Notifications;

use Administration\Events\UserDeletedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Mockery;
use Notifications\Dtos\NotificationCreateDto;
use Notifications\Enums\NotificationSeverityEnum;
use Notifications\Events\Listeners\BroadcastUserDeletedListener;
use Notifications\Services\Interfaces\NotificationServiceInterface;
use Tests\TestCase;

class BroadcastUserDeletedListenerTest extends TestCase
{
    private NotificationServiceInterface $notifications;
    private BroadcastUserDeletedListener $listener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notifications = Mockery::mock(NotificationServiceInterface::class);
        $this->listener = new BroadcastUserDeletedListener($this->notifications);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeEvent(): UserDeletedEvent
    {
        $deleted = new \Administration\Models\User();
        $deleted->id = 11;
        $deleted->name = 'Marek';
        $deleted->email = 'marek@example.com';
        $deleted->company_id = 100;

        $actor = new \Auth\Models\User();
        $actor->id = 5;
        $actor->name = 'Anna';

        return new UserDeletedEvent($deleted, $actor);
    }

    public function test_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, $this->listener);
        // Patrz GenerateThumbnailListenerTest — kolejka pochodzi z REDIS_QUEUE, nie z listenera.
        $this->assertFalse(property_exists($this->listener, 'queue'));
    }

    public function test_skips_actor_and_deleted_user(): void
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('where')->with('company_id', 100)->andReturnSelf();
        $builder->shouldReceive('whereNull')->with('deleted_at')->andReturnSelf();
        // Usunięty (11) i wykonawca (5) wypadają — sec_users jest soft-delete'owane, więc
        // wiersz usuniętego wciąż istnieje i bez tego dostałby powiadomienie o sobie.
        $builder->shouldReceive('whereNotIn')->with('id', [11, 5])->andReturnSelf();
        $builder->shouldReceive('pluck')->with('id')->andReturnSelf();
        $builder->shouldReceive('all')->andReturn([1, 2]);
        DB::shouldReceive('table')->with('sec_users')->andReturn($builder);

        $this->notifications->shouldReceive('create')->times(2)->with(Mockery::on(function (NotificationCreateDto $dto) {
            $data = $dto->getData();
            return $dto->getType() === 'user_deleted'
                && $dto->getSeverity() === NotificationSeverityEnum::Warning
                && $data['actor_name'] === 'Anna'
                && $data['user_name'] === 'Marek'
                && $dto->getLink() === '/users';
        }));

        $this->listener->handle($this->makeEvent());

        $this->assertTrue(true);
    }
}
