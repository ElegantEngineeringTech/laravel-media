<?php

namespace Finller\Media\Jobs;

use Finller\Media\Casts\GeneratedConversion;
use Finller\Media\MediaConversion;
use Finller\Media\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class ConversionJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public TemporaryDirectory $temporaryDirectory;

    public $deleteWhenMissingModels = true;

    public function __construct(public Media $media, public string $conversion)
    {
        $this->onConnection(config('media.queue_connection'));
        $this->onQueue(config('media.queue'));
    }

    /**
     * WithoutOverlapping middleware will cost you a try
     * If you have 10 conversions for the same media, you should allow at least 10 tries in your job/queue
     * Because each processing job will trigger a try to the other pending ones
     * ReleaseAfter value qhould always be longer than the time it takes to proceed the job
     */
    public function withoutOverlapping(): WithoutOverlapping
    {
        return (new WithoutOverlapping("media:{$this->media->id}"))
            ->shared()
            ->releaseAfter(config('media.queue_overlapping.release_after', 60))
            ->expireAfter(config('media.queue_overlapping.expire_after', 60 * 60));
    }

    public function uniqueId()
    {
        return "{$this->media->id}:{$this->conversion}";
    }

    public function middleware(): array
    {
        /**
         * When the connection is 'sync', overlapping jobs are skipped
         */
        if ($this->job?->getConnectionName() === 'sync') {
            return [];
        }

        return [
            $this->withoutOverlapping(),
        ];
    }

    public function getConversion(): ?MediaConversion
    {
        // @phpstan-ignore-next-line
        return $this->media->model?->getMediaConversion($this->media, $this->conversion);
    }

    public function isNestedConversion()
    {
        return count(explode('.', $this->conversion)) > 1;
    }

    public function getGeneratedParentConversion(): ?GeneratedConversion
    {
        if ($this->isNestedConversion()) {
            return $this->media->getGeneratedParentConversion($this->conversion);
        }

        return null;
    }

    public function makeTemporaryFileCopy(): string|false
    {
        if ($this->isNestedConversion()) {
            return $this->getGeneratedParentConversion()->makeTemporaryFileCopy($this->temporaryDirectory);
        }

        return $this->media->makeTemporaryFileCopy($this->temporaryDirectory);
    }

    public function getTemporaryDisk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::build([
            'driver' => 'local',
            'root' => $this->temporaryDirectory->path(),
        ]);
    }

    public function handle()
    {
        $this->start();

        try {
            $this->run();
        } catch (\Throwable $th) {

            $this->temporaryDirectory->delete();

            throw $th;
        }

        $this->end();
    }

    public function start()
    {
        $this->temporaryDirectory = (new TemporaryDirectory())
            ->location(storage_path('media-tmp'))
            ->deleteWhenDestroyed()
            ->create();
    }

    public function run()
    {
        //
    }

    public function end()
    {
        $this->temporaryDirectory->delete();

        $this->dispatchChildrenConversions();
    }

    protected function dispatchChildrenConversions()
    {
        $conversion = $this->getConversion();

        if (! $conversion || $conversion->conversions->isEmpty()) {
            return;
        }

        foreach ($conversion->conversions as $childConversion) {
            $childConversion->job->conversion = implode('.', [$this->conversion, $childConversion->job->conversion]);

            if ($childConversion->sync) {
                dispatch_sync($childConversion->job);
            } else {
                dispatch($childConversion->job);
            }
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array
     */
    public function tags()
    {
        return [
            'media',
            get_class($this),
            $this->conversion,
        ];
    }
}
