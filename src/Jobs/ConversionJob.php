<?php

namespace Finller\Media\Jobs;

use Finller\Media\MediaConversion;
use Finller\Media\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class ConversionJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?TemporaryDirectory $temporaryDirectory = null;

    public function __construct(public Media $media, public string $conversion)
    {
    }

    public function getConversion(): ?MediaConversion
    {
        // @phpstan-ignore-next-line
        return $this->media->model->getMediaConversion($this->media, $this->conversion);
    }

    public function uniqueId()
    {
        return "{$this->media->id}:{$this->conversion}";
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
        $this->temporaryDirectory?->delete();

        $this->dispatchChildrenConversions();
    }

    protected function dispatchChildrenConversions()
    {
        $conversion = $this->getConversion();

        if (! $conversion || $conversion->conversions->isEmpty()) {
            return;
        }

        foreach ($conversion->conversions as $childConversion) {
            $job = $childConversion->job;
            $job->conversion = implode('.', [$this->conversion, $job->conversion]);
            dispatch($job);
        }
    }
}
