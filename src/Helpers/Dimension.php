<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<'width'|'height', int>
 */
class Dimension implements Arrayable
{
    final public function __construct(
        public readonly int $width,
        public readonly int $height
    ) {
        //
    }

    public function getAspectRatio(): AspectRatio
    {
        if ($this->height == 0) {
            return new AspectRatio(0);
        }

        return new AspectRatio($this->width / $this->height);
    }

    public function isPortrait(): bool
    {
        return $this->height > $this->width;
    }

    public function pixels(): int
    {
        return $this->width * $this->height;
    }

    public function flip(): static
    {
        return new static($this->height, $this->width);
    }

    /**
     * The dimension after fitting inside the given width and/or height while keeping the aspect ratio,
     * like ffmpeg's scale filter with force_original_aspect_ratio=decrease.
     */
    public function scale(
        ?int $width = null,
        ?int $height = null,
        bool $upscale = true
    ): static {
        if (! $width && ! $height) {
            return new static($this->width, $this->height);
        }

        $factor = min(
            $width ? $width / $this->width : INF,
            $height ? $height / $this->height : INF,
            $upscale ? INF : 1,
        );

        return new static(
            (int) round($this->width * $factor),
            (int) round($this->height * $factor),
        );
    }

    public function fitInto(Dimension $max): bool
    {
        return $this->width <= $max->width
            && $this->height <= $max->height;
    }

    /**
     * @return array{ width: int, height: int }
     */
    public function toArray(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
