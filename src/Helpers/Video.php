<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

use Elegantly\Media\FFMpeg\FFMpeg;
use Elegantly\Media\Helpers\Contracts\HasDimension;
use Elegantly\Media\Helpers\Contracts\HasDuration;

class Video implements HasDimension, HasDuration
{
    public static function dimension(string $path): Dimension
    {
        [$width, $height, $rotation] = FFMpeg::make()->video()->dimensions($path, rotate: true);

        return new Dimension($width, $height);
    }

    public static function rotation(string $path): ?int
    {
        [$width, $height, $rotation] = FFMpeg::make()->video()->dimensions($path);

        return $rotation;
    }

    public static function duration(string $path): ?float
    {
        return FFMpeg::make()->video()->duration($path);
    }
}
