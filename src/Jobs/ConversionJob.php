<?php

namespace Finller\LaravelMedia\Jobs;

use Finller\LaravelMedia\Media;
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

    public TemporaryDirectory $temporaryDirectory;

    public function __construct(public Media $media, public string $conversion)
    {
        $this->temporaryDirectory = (new TemporaryDirectory())->deleteWhenDestroyed()->create();
    }

    public function uniqueId()
    {
        return "{$this->media->id}:{$this->conversion}";
    }

    public function handle()
    {
        //
    }
}
