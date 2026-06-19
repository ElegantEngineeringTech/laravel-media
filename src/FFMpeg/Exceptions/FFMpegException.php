<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg\Exceptions;

use Exception;

class FFMpegException extends Exception
{
    public static function executionFailed(int $code, string $command, string $error): self
    {
        return new self(implode("\n", [
            "Error {$code} Executing ffmpeg: ",
            '---',
            $command,
            '---',
            $error,
        ]), 500);
    }
}
