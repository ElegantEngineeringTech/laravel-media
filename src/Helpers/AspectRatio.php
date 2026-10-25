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

    public function getWidth(int $height): int
    {
        return (int) round($height * $this->value);
    }

    public function getHeight(int $width): int
    {
        return (int) round($width / $this->value);
    }
}
