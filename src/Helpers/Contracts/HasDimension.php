<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers\Contracts;

use Elegantly\Media\Helpers\Dimension;

interface HasDimension
{
    public static function dimension(string $path): ?Dimension;
}
