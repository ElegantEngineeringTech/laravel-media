<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg\Exceptions;

class AudioStreamNotFoundException extends FFMpegException
{
    public static function atPath(string $path): self
    {
        return new self("No audio stream found in file: {$path}");
    }
}
