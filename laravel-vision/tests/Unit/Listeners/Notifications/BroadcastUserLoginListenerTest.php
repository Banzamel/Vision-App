<?php

namespace Tests\Unit\Listeners\Notifications;

use Auth\Events\LoginEvent;
use Auth\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Mockery;
use Notifications\Dtos\NotificationCreateDto;
use Notifications\Events\Listeners\BroadcastUserLoginListener;
use Notifications\Services\Interfaces\NotificationServiceInterface;
use Tests\TestCase;

class BroadcastUserLoginListenerTest extends TestCase
{
    private NotificationServiceInterface $notifications;
    private BroadcastUserLoginListener $listener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notifications = Mockery::mock(NotificationServiceInterface::class);
        $this->listener = new BroadcastUserLoginListener($this->notifications);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, $this->listener);
        // Patrz GenerateThumbnailListenerTest — kolejka pochodzi z REDIS_QUEUE, nie z listenera.
        $this->assertFalse(property_exists($this->listener, 'queue'));
    }

    public function test_skips_actor_and_notifies_other_company_members(): void
    {
        $actor = new User();
        $actor->id = 5;
        $actor->name = 'Anna';
        $actor->company_id = 100;

        $builder = Mockery::mock();
        $builder->shouldReceive('where')->with('company_id', 100)->andReturnSelf();
        $builder->shouldReceive('where')->with('id', '!=', 5)->andReturnSelf();
        $builder->shouldReceive('pluck')->with('id')->andReturnSelf();
        $builder->shouldReceive('all')->andReturn([1, 2]);
        DB::shouldReceive('table')->with('sec_users')->andReturn($builder);

        $this->notifications->shouldReceive('create')->times(2)->with(Mockery::on(function (NotificationCreateDto $dto) {
            return $dto->getType() === 'user_login'
                && str_contains($dto->getMessage(), 'Anna');
        }));

        $this->listener->handle(new LoginEvent($actor));

        $this->assertTrue(true);
    }

    public function test_returns_early_when_actor_has_no_company(): void
    {
        $actor = new User();
        $actor->id = 1;
        $actor->name = 'Lonely';
        // company_id intentionally not set

        // No DB access, no notification creation expected.
        DB::shouldReceive('table')->never();
        $this->notifications->shouldNotReceive('create');

        $this->listener->handle(new LoginEvent($actor));

        $this->assertTrue(true);
    }
}
