<?php

namespace Finller\Media\Jobs;

use FFMpeg\Coordinate\TimeCode;
use Finller\Media\Models\Media;
use Illuminate\Support\Facades\File;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Spatie\ImageOptimizer\OptimizerChain;

class VideoPosterConversionJob extends ConversionJob
{
    public string $fileName;

    public function __construct(
        public Media $media,
        public string $conversion,
        public null|int|string|TimeCode $seconds = 0,
        public ?int $width = null,
        public ?int $height = null,
        public Fit $fit = Fit::Max,
        public ?OptimizerChain $optimizerChain = null,
        ?string $fileName = null,
    ) {
        parent::__construct($media, $conversion);

        $this->fileName = $fileName ?? $this->media->file_name;
    }

    public function run()
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
            conversion: $this->conversion,
            name: File::name($this->fileName)
        );
    }
}
