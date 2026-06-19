<?php

declare(strict_types=1);

namespace Elegantly\Media\Exceptions;

use Exception;

class FileContentNotFoundException extends Exception
{
    public static function atPath(string $path): self
    {
        return new self("Can't get file content at {$path}");
    }
}
