<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

use Elegantly\Media\Helpers\Contracts\HasDimension;
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
}
