<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg\Exceptions;

class VideoStreamNotFoundException extends FFMpegException
{
    public static function atPath(string $path): self
    {
        return new self("No video stream found in file: {$path}");
    }
}
