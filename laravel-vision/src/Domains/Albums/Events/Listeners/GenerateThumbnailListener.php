<?php

namespace Albums\Events\Listeners;

use Albums\Events\PhotoAddedEvent;
use Albums\Services\Interfaces\ThumbnailServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Listener that materialises a 400x300 JPEG thumbnail for a freshly synced photo.
 * Queued (Redis) so that AlbumSyncService is not blocked by GD work — when the user
 * opens the gallery before the worker has run, ThumbnailService falls back to on-the-fly generation.
 */
class GenerateThumbnailListener implements ShouldQueue
{
    // Bez $queue celowo — job trafia na kolejkę skonfigurowaną dla połączenia
    // (queue.connections.redis.queue → REDIS_QUEUE). Zaszycie tu 'default' rozjeżdżało
    // się z workerem, gdy REDIS_QUEUE wskazywał inną nazwę, i joby cicho nie schodziły.

    /**
     * @param ThumbnailServiceInterface $thumbnails Thumbnail generator service.
     */
    public function __construct(
        protected ThumbnailServiceInterface $thumbnails,
    ) {
    }

    /**
     * Generates the thumbnail for the photo carried by the event.
     *
     * @param PhotoAddedEvent $event Event fired by AlbumSyncService when a new photo row is upserted.
     * @return void
     */
    public function handle(PhotoAddedEvent $event): void
    {
        $this->thumbnails->generate($event->photo);
    }
}
