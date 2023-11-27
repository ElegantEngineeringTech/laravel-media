<?php

namespace Finller\LaravelMedia\Jobs;

use Finller\LaravelMedia\Media;
use Illuminate\Support\Facades\File;
use Spatie\Image\Image;
use Spatie\Image\Manipulations;

class OptimizedImageConversionJob extends ConversionJob
{
    public string $fileName;

    public function __construct(
        public Media $media,
        public string $conversion,
        public ?int $width = null,
        public ?int $height = null,
        public string $fitMethod = Manipulations::FIT_MAX,
        public array $optimizationOptions = [],
        string $fileName = null,
    ) {
        parent::__construct($media, $conversion);

        $this->fileName = $fileName ?? $this->media->file_name;
    }

    public function run()
    {
        $path = $this->media->makeTemporaryFileCopy($this->temporaryDirectory);

        $newPath = File::dirname($path).DIRECTORY_SEPARATOR.$this->fileName;

        Image::load($path)
            ->manipulate(function (Manipulations $manipulations) {
                if ($this->width || $this->height) {
                    $manipulations->fit($this->fitMethod, $this->width, $this->height);
                }

                $manipulations->optimize($this->optimizationOptions);
            })
            ->save($newPath);

        $this->media->storeConversion(
            file: $newPath,
            conversion: $this->conversion,
            name: File::name($this->fileName)
        );
    }
}
