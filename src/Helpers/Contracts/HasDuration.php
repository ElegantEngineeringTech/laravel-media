<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers\Contracts;

interface HasDuration
{
    /**
     * @return ?float The duration in milliseconds
     */
    public static function duration(string $path): ?float;
}
