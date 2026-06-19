<?php

declare(strict_types=1);

namespace Elegantly\Media\Exceptions;

use Exception;

class MediaFileStorageException extends Exception
{
    public static function storeFailed(string $path, string $disk, string $destination): self
    {
        return new self("Storing Media File '{$path}' to disk '{$disk}' at '{$destination}' failed.");
    }
}
