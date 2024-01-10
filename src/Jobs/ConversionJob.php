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
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class ConversionJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public TemporaryDirectory $temporaryDirectory;

    public function __construct(public Media $media, public string $conversion)
    {
        //
    }

    public function uniqueId()
    {
        return "{$this->media->id}:{$this->conversion}";
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

        $this->run();

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
}
