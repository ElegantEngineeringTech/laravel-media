<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<'width'|'height', float|int>
 */
class Dimension implements Arrayable
{
    final public function __construct(
        public readonly float|int $width,
        public readonly float|int $height
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

    public function flip(): static
    {
        return new static($this->height, $this->width);
    }

    public function fitInto(Dimension $max): bool
    {
        return $this->width <= $max->width
            && $this->height <= $max->height;
    }

    /**
     * @return array{ width: float|int, height: float|int }
     */
    public function toArray(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
