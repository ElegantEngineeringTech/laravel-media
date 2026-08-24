<?php

declare(strict_types=1);

namespace Elegantly\Media\Facades;

use Elegantly\Media\FFMpeg\Audio;
use Elegantly\Media\FFMpeg\Video;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Video video()
 * @method static Audio audio()
 *
 * @see Elegantly\Media\FFMpeg\FFMpeg
 */
class FFMpeg extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Elegantly\Media\FFMpeg\FFMpeg::class;
    }
}
