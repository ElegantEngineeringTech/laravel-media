<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

use Elegantly\Media\FFMpeg\Exceptions\FFMpegException;
use Elegantly\Media\FFMpeg\FFMpeg;
use Elegantly\Media\Helpers\Contracts\HasDimension;
use Elegantly\Media\Helpers\Contracts\HasDuration;

class Video implements HasDimension, HasDuration
{
    public static function dimension(string $path): ?Dimension
    {
        try {
            [$width, $height, $rotation] = FFMpeg::make()->video()->dimensions($path);
        } catch (FFMpegException $th) {
            return null;
        }

        if ($rotation && $rotation % 90 === 0 && $rotation % 180 !== 0) {
            return new Dimension($height, $width);
        }

        return new Dimension($width, $height);
    }

    public static function rotation(string $path): ?int
    {
        try {
            [$width, $height, $rotation] = FFMpeg::make()->video()->dimensions($path);
        } catch (FFMpegException $th) {
            return null;
        }

        return $rotation;
    }

    public static function duration(string $path): ?float
    {
        try {
            $duration = FFMpeg::make()->video()->duration($path);
        } catch (FFMpegException $th) {
            return null;
        }

        return $duration;
    }
}
