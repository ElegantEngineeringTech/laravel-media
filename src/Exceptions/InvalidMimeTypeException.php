<?php

declare(strict_types=1);

namespace Elegantly\Media\Exceptions;

use Exception;

class InvalidMimeTypeException extends Exception
{
    public static function notAccepted(?string $mime, array $acceptedMimeTypes): self
    {
        return new static(
            "Media file can't be stored: Invalid MIME type: {$mime}. Accepted MIME types are: ".implode(', ', $acceptedMimeTypes),
            415
        );
    }
}
