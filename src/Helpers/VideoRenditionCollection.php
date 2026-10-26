<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * @extends Collection<int, VideoRendition>
 */
class VideoRenditionCollection extends Collection
{
    /**
     * @param  int[]  $values
     */
    public function onlySizes(array $values, bool $strict = false): static
    {
        $filtered = $this->whereIn('size', $values)->values();

        if ($strict) {
            $sizes = array_column($this->all(), 'size');

            $missing = array_diff($values, $sizes);

            if ($missing) {
                throw new InvalidArgumentException(sprintf(
                    'Unknown video rendition %s %s. Available sizes: %s.',
                    count($missing) > 1 ? 'sizes' : 'size',
                    implode(', ', $missing),
                    implode(', ', array_unique($sizes)),
                ));
            }
        }

        return $filtered;
    }

    public function supportedBy(Dimension $source): static
    {
        return $this
            ->filter(fn ($rendition) => $rendition->isSupportedBy($source))
            ->values();
    }

    /**
     * The rendition whose pixel count is the closest to the dimension, as bitrates scale with the pixel count.
     * Pixel counts are compared on a log scale, like the renditions sizes grow geometrically.
     */
    public function getClosestBy(Dimension $dimension): ?VideoRendition
    {
        $pixels = max(1, $dimension->pixels());

        return $this
            ->sortBy(fn ($rendition) => abs(log($pixels / $rendition->dimension()->pixels())))
            ->first();
    }

    public function getClosestToKey(int $size): ?int
    {
        return array_key_first(
            $this->sortBy(fn ($rendition) => abs($rendition->size - $size))->all()
        );
    }

    public function setMaxBitrate(null|string|float|int|Bitrate $value): static
    {
        return $this->map(fn ($rendition) => $rendition->setMaxBitrate($value));
    }

    public function setAudioBitrate(string|float|int|Bitrate $value): static
    {
        return $this->map(fn ($rendition) => $rendition->setAudioBitrate($value));
    }

    public function setMaxAudioBitrate(null|string|float|int|Bitrate $value): static
    {
        return $this->map(fn ($rendition) => $rendition->setMaxAudioBitrate($value));
    }
}
