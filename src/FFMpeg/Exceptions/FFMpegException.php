<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg\Exceptions;

use Exception;

class FFMpegException extends Exception
{
    public static function executionFailed(string $binary, int $code, string $command, string $error): self
    {
        return new self(implode("\n", [
            "Error {$code} executing ".basename($binary).':',
            '---',
            $command,
            '---',
            $error,
        ]), 500);
    }
}
