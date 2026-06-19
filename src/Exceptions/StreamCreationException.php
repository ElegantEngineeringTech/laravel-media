<?php

declare(strict_types=1);

namespace Elegantly\Media\Exceptions;

use Exception;

class StreamCreationException extends Exception
{
    public static function memoryStreamFailed(): self
    {
        return new self('PHP Stream creation failed.');
    }
}
