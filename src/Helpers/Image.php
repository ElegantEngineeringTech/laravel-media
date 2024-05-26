<?php

namespace Elegantly\Media\Helpers;

use FFMpeg\Coordinate\AspectRatio;
use FFMpeg\Coordinate\Dimension;
use Spatie\Image\Image as SpatieImage;

class Image implements HasDimension
{
    public static function dimension(string $path): Dimension
    {
        $file = SpatieImage::load($path);

        return new Dimension(
            width: $file->getWidth(),
            height: $file->getHeight(),
        );
    }

    public static function ratio(string $path, bool $forceStandards = true): AspectRatio
    {
        return static::dimension($path)->getRatio($forceStandards);
    }
}
