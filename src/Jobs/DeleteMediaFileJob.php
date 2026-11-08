<?php

declare(strict_types=1);

namespace Elegantly\Media\Jobs;

use Elegantly\Media\Exceptions\MediaFileStorageException;
use Elegantly\Media\MediaServiceProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class DeleteMediaFileJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public function __construct(
        public string $disk,
        public string $path,
    ) {
        $this->onConnection(MediaServiceProvider::getQueueConnection());
        $this->onQueue(MediaServiceProvider::getQueue());
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return "{$this->disk}:{$this->path}";
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 900, 3_600];
    }

    public function handle(): void
    {
        $storage = Storage::disk($this->disk);

        if (! $storage->delete($this->path)) {
            throw MediaFileStorageException::deleteFailed($this->path, $this->disk);
        }
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
