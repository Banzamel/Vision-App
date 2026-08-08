<?php

namespace Tests\Unit\Listeners\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Mockery;
use Notifications\Dtos\NotificationCreateDto;
use Notifications\Enums\NotificationSeverityEnum;
use Notifications\Events\Listeners\BroadcastObjectDeletedListener;
use Notifications\Services\Interfaces\NotificationServiceInterface;
use Objects\Events\ObjectDeletedEvent;
use Objects\Models\VisionObject;
use Tests\TestCase;

class BroadcastObjectDeletedListenerTest extends TestCase
{
    private NotificationServiceInterface $notifications;
    private BroadcastObjectDeletedListener $listener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notifications = Mockery::mock(NotificationServiceInterface::class);
        $this->listener = new BroadcastObjectDeletedListener($this->notifications);
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
        $object = new VisionObject();
        $object->id = 42;
        $object->name = 'Budynek A';
        $object->company_id = 100;

        $builder = Mockery::mock();
        $builder->shouldReceive('where')->with('company_id', 100)->andReturnSelf();
        // Bez tego warunku powiadomienia trafiłyby też do soft-delete'owanych kont.
        $builder->shouldReceive('whereNull')->with('deleted_at')->andReturnSelf();
        $builder->shouldReceive('pluck')->with('id')->andReturnSelf();
        $builder->shouldReceive('all')->andReturn([1, 2, 3]);
        DB::shouldReceive('table')->with('sec_users')->andReturn($builder);

        $this->notifications->shouldReceive('create')->times(3)->with(Mockery::on(function (NotificationCreateDto $dto) {
            return $dto->getType() === 'object_deleted'
                && $dto->getSeverity() === NotificationSeverityEnum::Warning
                && $dto->getData()['object_name'] === 'Budynek A'
                // Cel już nie istnieje, więc link musi zostać pusty.
                && $dto->getLink() === null;
        }));

        $this->listener->handle(new ObjectDeletedEvent($object));

        $this->assertTrue(true);
    }

    public function test_returns_early_when_object_has_no_company(): void
    {
        $object = new VisionObject();
        $object->id = 7;
        $object->name = 'Sierota';
        // company_id intentionally not set

        DB::shouldReceive('table')->never();
        $this->notifications->shouldNotReceive('create');

        $this->listener->handle(new ObjectDeletedEvent($object));

        $this->assertTrue(true);
    }
}
