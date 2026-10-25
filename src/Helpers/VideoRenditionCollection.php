<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

use Illuminate\Support\Collection;

/**
 * @extends Collection<int, VideoRendition>
 */
class VideoRenditionCollection extends Collection
{
    /**
     * @return Collection<int, string>
     */
    public function audioBitrates(): Collection
    {
        return $this
            ->toBase()
            ->map(fn ($rendition) => $rendition->audioBitrate->format());
    }

    public function supportedBy(Dimension $source): static
    {
        return $this
            ->filter(fn ($rendition) => $rendition->isSupportedBy($source))
            ->values();
    }

    public function getBestSupportedBy(Dimension $source): VideoRendition
    {
        $items = $this->sortBy('size');

        return $items->supportedBy($source)->last() ?? $items->first();
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
        $value = Bitrate::parse($value);

        if ($value === null || $value->isZero()) {
            return $this;
        }

        return $this->map(fn ($rendition) => $rendition->setMaxBitrate($value));
    }

    public function setAudioBitrate(string|float|int|Bitrate $value): static
    {
        return $this->map(fn ($rendition) => new VideoRendition([
            ...$rendition->toArray(),
            'audioBitrate' => $value,
        ]));
    }

    public function setMaxAudioBitrate(null|string|float|int|Bitrate $value): static
    {
        $value = Bitrate::parse($value);

        if ($value === null || $value->isZero()) {
            return $this;
        }

        return $this->map(fn ($rendition) => new VideoRendition([
            ...$rendition->toArray(),
            'audioBitrate' => $rendition->audioBitrate->max($value),
        ]));

    }
}
