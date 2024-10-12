<?php

namespace Elegantly\Media\Jobs;

use Elegantly\Media\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * This job will take care of deleting Media associated with models
 * Deleting a media can take some time or even fail.
 * To prevent failure when a Model is deleted, the media are individually deleted by this job.
 */
class DeleteModelMediaJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Media $media)
    {
        $this->media = $media->withoutRelations();

        /** @var ?string $connection */
        $connection = config('media.queue_connection');
        /** @var ?string $queue */
        $queue = config('media.queue');

        $this->onConnection($connection);
        $this->onQueue($queue);
    }

    public function uniqueId(): string
    {
        return (string) $this->media->id;
    }

    public function handle(): void
    {
        $this->media->delete();
    }

    /**
     * @return array<array-key, int|string>
     */
    public function tags(): array
    {
        return [
            'media',
            get_class($this),
        ];
    }
}
