<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

class AspectRatio
{
    public function __construct(
        public readonly int|float $value,
    ) {
        //
    }

    public function getWidth(int|float $height): int|float
    {
        return $height * $this->value;
    }

    public function getHeight(int|float $width): int|float
    {
        return $width / $this->value;
    }
}
