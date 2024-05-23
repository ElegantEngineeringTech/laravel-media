<?php

namespace Finller\Media\Jobs;

use FFMpeg\Filters\Video\ResizeFilter;
use FFMpeg\Format\FormatInterface;
use FFMpeg\Format\Video\X264;
use Finller\Media\Models\Media;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\File;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class OptimizedVideoConversionJob extends MediaConversionJob
{
    public string $fileName;

    public function withoutOverlapping(): WithoutOverlapping
    {
        return (new WithoutOverlapping("media:{$this->media->id}"))
            ->shared()
            ->releaseAfter(now()->addMinutes(10))
            ->expireAfter(now()->addMinutes(30));
    }

    public function __construct(
        public Media $media,
        ?string $queue = null,
        public ?int $width = null,
        public ?int $height = null,
        public FormatInterface $format = new X264,
        public string $fitMethod = ResizeFilter::RESIZEMODE_FIT,
        public bool $forceStandards = false,
        ?string $fileName = null,
    ) {
        parent::__construct($media, $queue);

        $this->fileName = $fileName ?? $this->media->file_name;
    }

    public function run(): void
    {
        $temporaryDisk = $this->getTemporaryDisk();
        $path = $this->makeTemporaryFileCopy();

        // @phpstan-ignore-next-line
        FFMpeg::fromDisk($temporaryDisk)
            ->open(File::basename($path))
            ->export()
            ->inFormat($this->format)
            ->resize($this->width, $this->height, $this->fitMethod, $this->forceStandards)
            ->save($this->fileName);

        $this->media->storeConversion(
            file: $temporaryDisk->path($this->fileName),
            conversion: $this->conversionName,
            name: File::name($this->fileName)
        );
    }
}
