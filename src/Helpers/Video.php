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
        [$width, $height, $rotation] = FFMpeg::make()->video()->dimensions($path);

        if ($rotation && $rotation % 90 === 0 && $rotation % 180 !== 0) {
            return new Dimension($height, $width);
        }

        return new Dimension($width, $height);
    }

    public static function rotation(string $path): ?int
    {
        [$width, $height, $rotation] = FFMpeg::make()->video()->dimensions($path);

        return $rotation;
    }

    public static function duration(string $path): ?float
    {
        $duration = FFMpeg::make()->video()->duration($path);

        return $duration;
    }
}
