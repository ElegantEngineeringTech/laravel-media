<?php

declare(strict_types=1);

namespace Elegantly\Media\Exceptions;

use InvalidArgumentException;

class InvalidDataUrlException extends InvalidArgumentException
{
    public static function missingComma(): self
    {
        return new self('Invalid data URL: the data must be preceded by a comma.');
    }

    public static function invalidBase64(): self
    {
        return new self('Invalid data URL: the data is not valid base64.');
    }
}
