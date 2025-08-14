<?php

declare(strict_types=1);

namespace Elegantly\Media\Converters\Concerns;

/**
 * @property ?int $width
 * @property ?int $height
 */
trait HasDimensions
{
    protected function round(null|int|float $value, int $mulitple = 1): ?int
    {
        if ($value === null) {
            return $value;
        }

        return (int) round($value / $mulitple) * $mulitple;
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    protected function getDimensions(?int $width, ?int $height, int $multiple = 1): array
    {
        if ($width === null || $height === null) {
            return [$this->width, $this->height];
        }

        if ($this->width && $this->height) {
            if ($this->width > $width && $this->height > $height) {
                if ($this->width - $width > $this->height - $height) {
                    return [
                        $this->round($width, $multiple),
                        $this->round($width * $this->height / $this->width, $multiple),
                    ];
                } else {
                    return [
                        $this->round($height * $this->width / $this->height, $multiple),
                        $this->round($height, $multiple),
                    ];
                }
            }

            if ($this->width > $width) {
                return [
                    $this->round($width, $multiple),
                    $this->round($width * $this->height / $this->width, $multiple),
                ];
            }

            if ($this->height > $height) {
                return [
                    $this->round($this->width * $height / $width, $multiple),
                    $this->round($height, $multiple),
                ];
            }

            return [
                $this->round($this->width * $height / $width, $multiple),
                $this->round($this->height * $width / $height, $multiple),
            ];
        }

        if ($this->width && $this->width > $width) {
            return [
                $this->round($width, $multiple),
                null,
            ];
        }

        if ($this->height && $this->height > $height) {
            return [
                null,
                $this->round($height, $multiple),
            ];
        }

        return [
            $this->round($this->width, $multiple),
            $this->round($this->height, $multiple),
        ];
    }
}
