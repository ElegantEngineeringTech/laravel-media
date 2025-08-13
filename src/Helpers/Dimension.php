<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

class Dimension
{
    public function __construct(
        public readonly float|int $width,
        public readonly float|int $height
    ) {
        //
    }

    public function getAspectRatio(): AspectRatio
    {
        return new AspectRatio($this->width / $this->height);
    }
}
