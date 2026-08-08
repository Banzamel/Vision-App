<?php

namespace Tests\Unit\Listeners\Notifications;

use Albums\Events\AlbumCreatedEvent;
use Albums\Models\Album;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Mockery;
use Notifications\Dtos\NotificationCreateDto;
use Notifications\Enums\NotificationSeverityEnum;
use Notifications\Events\Listeners\BroadcastAlbumCreatedListener;
use Notifications\Services\Interfaces\NotificationServiceInterface;
use Tests\TestCase;

class BroadcastAlbumCreatedListenerTest extends TestCase
{
    private NotificationServiceInterface $notifications;
    private BroadcastAlbumCreatedListener $listener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notifications = Mockery::mock(NotificationServiceInterface::class);
        $this->listener = new BroadcastAlbumCreatedListener($this->notifications);
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

    public function test_creates_notification_for_each_company_user(): void
    {
        $album = $this->makeAlbum(companyId: 100, cameraName: 'Brama', date: '2026-04-27');
        $album->id = 555;

        // DB facade is used directly — fake the user-id query.
        $builder = Mockery::mock();
        $builder->shouldReceive('where')->with('company_id', 100)->andReturnSelf();
        $builder->shouldReceive('pluck')->with('id')->andReturnSelf();
        $builder->shouldReceive('all')->andReturn([1, 2, 3]);
        DB::shouldReceive('table')->with('sec_users')->andReturn($builder);

        // One notification per recipient, with the expected message and link.
        $this->notifications->shouldReceive('create')->times(3)->with(Mockery::on(function (NotificationCreateDto $dto) {
            return $dto->getCompanyId() === 100
                && $dto->getType() === 'album_created'
                && $dto->getSeverity() === NotificationSeverityEnum::Info
                && $dto->getLink() === '/objects?album=555'
                && str_contains($dto->getMessage(), 'Brama')
                && str_contains($dto->getMessage(), '2026-04-27');
        }));

        $this->listener->handle(new AlbumCreatedEvent($album));

        $this->assertTrue(true);
    }

    public function test_uses_dash_placeholders_when_camera_or_date_missing(): void
    {
        $album = new Album();
        $album->id = 1;
        $album->company_id = 100;
        // No camera relation, no date

        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('pluck')->andReturnSelf();
        $builder->shouldReceive('all')->andReturn([7]);
        DB::shouldReceive('table')->andReturn($builder);

        $this->notifications->shouldReceive('create')->once()->with(Mockery::on(function (NotificationCreateDto $dto) {
            // Both placeholders should appear when the album has no camera/date.
            return str_contains($dto->getMessage(), '—');
        }));

        $this->listener->handle(new AlbumCreatedEvent($album));

        $this->assertTrue(true);
    }

    /**
     * Helper that produces an Album with a camera relation already populated, so the listener can
     * read camera->name without hitting Eloquent.
     */
    private function makeAlbum(int $companyId, string $cameraName, string $date): Album
    {
        $album = Mockery::mock(Album::class)->makePartial();
        $album->company_id = $companyId;

        $camera = (object) ['name' => $cameraName];
        $album->shouldReceive('getAttribute')->with('camera')->andReturn($camera);
        $album->shouldReceive('getAttribute')->with('date')->andReturn(\Illuminate\Support\Carbon::parse($date));
        $album->shouldReceive('getAttribute')->with('id')->andReturn(555);
        $album->shouldReceive('getAttribute')->with('company_id')->andReturn($companyId);

        return $album;
    }
}
