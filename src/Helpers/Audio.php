<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

use Elegantly\Media\FFMpeg\Exceptions\FFMpegException;
use Elegantly\Media\FFMpeg\FFMpeg;

class Audio
{
    public static function duration(string $path): ?float
    {
        try {
            $duration = FFMpeg::make()->audio()->duration($path);
        } catch (FFMpegException $th) {
            return null;
        }

        return $duration;
    }
}
