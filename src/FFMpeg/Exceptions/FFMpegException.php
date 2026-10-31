<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg\Exceptions;

use Exception;

class FFMpegException extends Exception
{
    /**
     * The stderr output of the failed process.
     */
    public ?string $error = null;

    public static function executionFailed(string $binary, int $code, string $command, string $error): self
    {
        $exception = new self(implode("\n", [
            "Error {$code} executing ".basename($binary).':',
            '---',
            $command,
            '---',
            $error,
        ]), 500);

        $exception->error = $error;

        return $exception;
    }

    /**
     * Whether the input itself is invalid (corrupted, truncated or not a media file),
     * as opposed to a missing file or a misconfigured binary.
     */
    public function isInvalidData(): bool
    {
        return
            $this->error !== null &&
            str_contains($this->error, 'Invalid data found when processing input');
    }
}
