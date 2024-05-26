<?php

namespace Elegantly\Media\Jobs;

use Elegantly\Media\Models\Media;
use FFMpeg\Coordinate\TimeCode;
use Illuminate\Support\Facades\File;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Spatie\ImageOptimizer\OptimizerChain;

class VideoPosterConversionJob extends MediaConversionJob
{
    public string $fileName;

    public function __construct(
        public Media $media,
        ?string $queue = null,
        public null|int|string|TimeCode $seconds = 0,
        public ?int $width = null,
        public ?int $height = null,
        public Fit $fit = Fit::Contain,
        public ?OptimizerChain $optimizerChain = null,
        ?string $fileName = null,
    ) {
        parent::__construct($media, $queue);

        $this->fileName = $fileName ?? "{$this->media->name}.jpg";
    }

    public function run(): void
    {
        $temporaryDisk = $this->getTemporaryDisk();
        $path = $this->makeTemporaryFileCopy();

        FFMpeg::fromDisk($temporaryDisk)
            ->open(File::basename($path))
            ->getFrameFromSeconds($this->seconds)
            ->export()
            ->save($this->fileName);

        Image::load($temporaryDisk->path($this->fileName))
            ->fit($this->fit, $this->width, $this->height)
            ->optimize($this->optimizerChain)
            ->save();

        $this->media->storeConversion(
            file: $temporaryDisk->path($this->fileName),
            conversion: $this->conversionName,
            name: File::name($this->fileName)
        );
    }
}
