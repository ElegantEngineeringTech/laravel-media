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

class MediaConversionJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public TemporaryDirectory $temporaryDirectory;

    public $deleteWhenMissingModels = true;

    /**
     * Path of the conversion
     */
    public string $conversionName;

    public function __construct(
        public Media $media,
        ?string $queue = null,
    ) {
        $this->media = $media->withoutRelations();
        $this->onConnection(config('media.queue_connection'));
        $this->onQueue($queue ?? config('media.queue'));
    }

    public function setConversionName(string $conversionName): static
    {
        $this->conversionName = $conversionName;

        return $this;
    }

    public function uniqueId(): string
    {
        return "{$this->media->id}:{$this->conversionName}";
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

    public function middleware(): array
    {
        /**
         * Skip overlapping job with sync queue or it will prevent jobs to be running
         */
        if ($this->job?->getConnectionName() === 'sync') {
            return [];
        }

        return [
            $this->withoutOverlapping(),
        ];
    }

    public function getMediaConversion(): ?MediaConversion
    {
        return $this->media->model?->getMediaConversion($this->media, $this->conversionName);
    }

    public function isNestedConversion(): bool
    {
        return count(explode('.', $this->conversionName)) > 1;
    }

    public function getGeneratedParentConversion(): ?GeneratedConversion
    {
        if ($this->isNestedConversion()) {
            return $this->media->getGeneratedParentConversion($this->conversionName);
        }

        return null;
    }

    public function getTemporaryDisk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::build([
            'driver' => 'local',
            'root' => $this->temporaryDirectory->path(),
        ]);
    }

    public function makeTemporaryFileCopy(): string|false
    {
        if ($this->isNestedConversion()) {
            return $this->getGeneratedParentConversion()->makeTemporaryFileCopy($this->temporaryDirectory);
        }

        return $this->media->makeTemporaryFileCopy($this->temporaryDirectory);
    }

    public function handle(): void
    {
        $this->init();

        try {
            $this->run();
        } catch (\Throwable $th) {

            $this->temporaryDirectory->delete();

            throw $th;
        }

        $this->destroy();
    }

    public function init(): void
    {
        $this->temporaryDirectory = (new TemporaryDirectory())
            ->location(storage_path('media-tmp'))
            ->deleteWhenDestroyed()
            ->create();
    }

    public function run(): void
    {
        //
    }

    /**
     * Cleanup temporary files and dispatch children conversions
     */
    public function destroy(): void
    {
        $this->temporaryDirectory->delete();

        $this->dispatchChildrenConversions();
    }

    protected function dispatchChildrenConversions(): void
    {
        $childrenConversions = $this->getMediaConversion()->getConversions($this->media);

        foreach ($childrenConversions as $childConversion) {
            $mediaConversionJob = $childConversion->getJob();

            $mediaConversionJob->setConversionName("{$this->conversionName}.{$mediaConversionJob->conversionName}");

            dispatch($mediaConversionJob);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'media',
            get_class($this),
            $this->conversionName,
        ];
    }
}
