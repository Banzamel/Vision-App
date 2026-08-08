<?php

namespace Tests\Unit\Listeners\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Mockery;
use Notifications\Dtos\NotificationCreateDto;
use Notifications\Enums\NotificationSeverityEnum;
use Notifications\Events\Listeners\BroadcastCameraDeletedListener;
use Notifications\Services\Interfaces\NotificationServiceInterface;
use Objects\Events\CameraDeletedEvent;
use Objects\Models\Camera;
use Tests\TestCase;

class BroadcastCameraDeletedListenerTest extends TestCase
{
    private NotificationServiceInterface $notifications;
    private BroadcastCameraDeletedListener $listener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notifications = Mockery::mock(NotificationServiceInterface::class);
        $this->listener = new BroadcastCameraDeletedListener($this->notifications);
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

    public function test_notifies_every_active_company_member(): void
    {
        $camera = new Camera();
        $camera->id = 9;
        $camera->name = 'Wejscie glowne';
        $camera->company_id = 100;

        $builder = Mockery::mock();
        $builder->shouldReceive('where')->with('company_id', 100)->andReturnSelf();
        $builder->shouldReceive('whereNull')->with('deleted_at')->andReturnSelf();
        $builder->shouldReceive('pluck')->with('id')->andReturnSelf();
        $builder->shouldReceive('all')->andReturn([4, 5]);
        DB::shouldReceive('table')->with('sec_users')->andReturn($builder);

        $this->notifications->shouldReceive('create')->times(2)->with(Mockery::on(function (NotificationCreateDto $dto) {
            return $dto->getType() === 'camera_deleted'
                && $dto->getSeverity() === NotificationSeverityEnum::Warning
                && $dto->getData()['camera_name'] === 'Wejscie glowne';
        }));

        $this->listener->handle(new CameraDeletedEvent($camera));

        $this->assertTrue(true);
    }

    public function test_returns_early_when_camera_has_no_company(): void
    {
        $camera = new Camera();
        $camera->id = 3;
        $camera->name = 'Sierota';
        // company_id intentionally not set

        DB::shouldReceive('table')->never();
        $this->notifications->shouldNotReceive('create');

        $this->listener->handle(new CameraDeletedEvent($camera));

        $this->assertTrue(true);
    }
}
