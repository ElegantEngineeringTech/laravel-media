<?php

declare(strict_types=1);

namespace Elegantly\Media\Exceptions;

use Exception;

class MediaFileStorageException extends Exception
{
    public static function deleteFailed(string $path, string $disk): self
    {
        return new self("Deleting Media File '{$path}' from disk '{$disk}' failed.");
    }

    public static function storeFailed(string $path, string $disk, string $destination): self
    {
        return new self("Storing Media File '{$path}' to disk '{$disk}' at '{$destination}' failed.");
    }

    public static function renameFailed(string $path, string $disk, string $destination): self
    {
        return new self("Renaming Media File '{$path}' to '{$destination}' on disk '{$disk}' failed.");
    }

    public static function renameTargetExists(string $path, string $disk, string $destination): self
    {
        return new self("Renaming Media File '{$path}' on disk '{$disk}' failed: '{$destination}' already exists.");
    }
}
