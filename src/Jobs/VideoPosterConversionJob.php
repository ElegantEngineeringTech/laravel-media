<?php

namespace Finller\Media\Jobs;

use FFMpeg\Coordinate\TimeCode;
use Finller\Media\Models\Media;
use Illuminate\Support\Facades\File;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Spatie\Image\Image;
use Spatie\Image\Manipulations;

class VideoPosterConversionJob extends ConversionJob
{
    public string $fileName;

    public function __construct(
        public Media $media,
        public string $conversion,
        public null|int|string|TimeCode $seconds = 0,
        public ?int $width = null,
        public ?int $height = null,
        public string $fitMethod = Manipulations::FIT_MAX,
        public array $optimizationOptions = [],
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
            ->manipulate(function (Manipulations $manipulations) {
                if ($this->width || $this->height) {
                    $manipulations->fit($this->fitMethod, $this->width, $this->height);
                }

                $manipulations->optimize($this->optimizationOptions);
            })
            ->save();

        $this->media->storeConversion(
            file: $temporaryDisk->path($this->fileName),
            conversion: $this->conversion,
            name: File::name($this->fileName)
        );
    }
}
