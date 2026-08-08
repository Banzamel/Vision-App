<?php

namespace Tests\Unit\Listeners\Albums;

use Albums\Events\Listeners\GenerateThumbnailListener;
use Albums\Events\PhotoAddedEvent;
use Albums\Models\Photo;
use Albums\Services\Interfaces\ThumbnailServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Mockery;
use Tests\TestCase;

class GenerateThumbnailListenerTest extends TestCase
{
    public function test_implements_should_queue(): void
    {
        $listener = new GenerateThumbnailListener(Mockery::mock(ThumbnailServiceInterface::class));

        $this->assertInstanceOf(ShouldQueue::class, $listener);
        // Nazwa kolejki NIE może być zaszyta w listenerze — job ma iść na kolejkę
        // z konfiguracji połączenia (REDIS_QUEUE), tę samą, której słucha worker.
        $this->assertFalse(property_exists($listener, 'queue'));
    }

    public function test_handle_forwards_photo_to_thumbnail_service(): void
    {
        $thumbnails = Mockery::mock(ThumbnailServiceInterface::class);
        $photo = new Photo();
        $thumbnails->shouldReceive('generate')->once()->with($photo);

        $listener = new GenerateThumbnailListener($thumbnails);
        $listener->handle(new PhotoAddedEvent($photo));

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
