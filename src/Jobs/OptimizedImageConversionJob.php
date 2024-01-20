<?php

namespace Finller\Media\Jobs;

use Finller\Media\Models\Media;
use Illuminate\Support\Facades\File;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Spatie\ImageOptimizer\OptimizerChain;

class OptimizedImageConversionJob extends ConversionJob
{
    public string $fileName;

    public function __construct(
        public Media $media,
        public string $conversion,
        public ?int $width = null,
        public ?int $height = null,
        public Fit $fit = Fit::Contain,
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

        $newPath = $temporaryDisk->path($this->fileName);

        Image::load($path)
            ->fit($this->fit, $this->width, $this->height)
            ->optimize($this->optimizerChain)
            ->save($newPath);

        $this->media->storeConversion(
            file: $newPath,
            conversion: $this->conversion,
            name: File::name($this->fileName)
        );
    }
}
