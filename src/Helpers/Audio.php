<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

use Elegantly\Media\Facades\FFMpeg;

class Audio
{
    public static function duration(string $path): float
    {
        return FFMpeg::audio()->duration($path);
    }
}
