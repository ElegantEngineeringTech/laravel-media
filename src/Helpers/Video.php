<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

use Elegantly\Media\Facades\FFMpeg;
use Elegantly\Media\Helpers\Contracts\HasDimension;
use Elegantly\Media\Helpers\Contracts\HasDuration;

class Video implements HasDimension, HasDuration
{
    public static function dimension(string $path): Dimension
    {
        return FFMpeg::video()->dimension($path, rotate: true);
    }

    public static function rotation(string $path): ?int
    {
        return FFMpeg::video()->rotation($path);
    }

    public static function duration(string $path): float
    {
        return FFMpeg::video()->duration($path);
    }
}
