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

    /**
     * Truncated like ffmpeg, so a display size matches the size of the scaled video.
     */
    public function getWidth(int $height): int
    {
        return (int) ($height * $this->value);
    }

    /**
     * Truncated like ffmpeg, so a display size matches the size of the scaled video.
     */
    public function getHeight(int $width): int
    {
        return (int) ($width / $this->value);
    }
}
