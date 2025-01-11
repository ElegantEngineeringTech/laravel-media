<?php

declare(strict_types=1);

namespace Elegantly\Media\Jobs;

use Elegantly\Media\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MediaConversionJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Media $media,
        public string $conversion,
    ) {
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
        return "{$this->media->id}:{$this->conversion}";
    }

    public function handle(): void
    {
        $this->media->executeConversion($this->conversion);
    }

    /**
     * @return array<array-key, int|string>
     */
    public function tags(): array
    {
        return [
            'media',
            "conversion:{$this->conversion}",
            get_class($this->media).':'.$this->media->id,
            "{$this->media->model_type}:{$this->media->model_id}",
        ];
    }
}
