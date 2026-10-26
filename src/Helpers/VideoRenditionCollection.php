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

    public function getBestSupportedBy(Dimension $source): ?VideoRendition
    {
        return $this->sortBy('size')->supportedBy($source)->last();
    }

    public function getClosestTo(int $size): ?VideoRendition
    {
        return $this->sortBy(fn ($rendition) => abs($rendition->size - $size))->first();
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
