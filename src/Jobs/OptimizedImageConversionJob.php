<?php

namespace Finller\LaravelMedia\Jobs;

use Finller\LaravelMedia\Media;
use Spatie\Image\Image;
use Spatie\Image\Manipulations;

class OptimizedImageConversionJob extends ConversionJob
{
    public function __construct(public Media $media, public string $conversion, public ?int $width = null, public ?int $height = null)
    {
        parent::__construct($media, $conversion);
    }

    public function handle()
    {
        $path = $this->media->makeTemporaryFileCopy($this->temporaryDirectory);

        Image::load($path)
            ->manipulate(function (Manipulations $manipulations) {
                if ($this->width || $this->height) {
                    $manipulations->fit(Manipulations::FIT_MAX, $this->width, $this->height);
                }

                $manipulations->optimize();
            })
            ->save();

        $this->media->storeConversion($path, $this->conversion, $this->media->name);
    }
}
