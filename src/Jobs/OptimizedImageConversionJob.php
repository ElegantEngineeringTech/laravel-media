<?php

namespace Finller\LaravelMedia\Jobs;

use Finller\LaravelMedia\Media;
use Illuminate\Support\Facades\File;
use Spatie\Image\Image;
use Spatie\Image\Manipulations;

class OptimizedImageConversionJob extends ConversionJob
{
    public string $file_name;

    public function __construct(
        public Media $media,
        public string $conversion,
        public ?int $width = null,
        public ?int $height = null,
        public string $fitMethod = Manipulations::FIT_MAX,
        public array $optimizationOptions = [],
        ?string $file_name = null,
    ) {
        parent::__construct($media, $conversion);

        $this->file_name = $file_name ?? $this->media->file_name;
    }

    public function handle()
    {
        $path = $this->media->makeTemporaryFileCopy($this->temporaryDirectory);

        $newPath = File::dirname($path) . DIRECTORY_SEPARATOR . $this->file_name;

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
            name: File::name($this->file_name)
        );
    }
}
