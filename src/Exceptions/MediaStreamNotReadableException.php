<?php

declare(strict_types=1);

namespace Elegantly\Media\Exceptions;

use Exception;
use Elegantly\Media\Models\Media;

class MediaStreamNotReadableException extends Exception
{
    public static function forMedia(Media $media): self
    {
        return new self("[Media:{$media->id}] Can't read stream at {$media->path} and disk {$media->disk}.");
    }
}
