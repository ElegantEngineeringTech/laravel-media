<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

use Illuminate\Support\Collection;

/**
 * @extends Collection<int, HlsVariant>
 */
class HlsVariants extends Collection
{
    public static function defaults(): static
    {
        // @phpstan-ignore-next-line
        return new static([
            new HlsVariant(['name' => '2160p', 'size' => 2160, 'maxrate' => '26750k', 'bufsize' => '37500k', 'audioBitrate' => '128k']),
            new HlsVariant(['name' => '1440p', 'size' => 1440, 'maxrate' => '8560k', 'bufsize' => '12000k', 'audioBitrate' => '128k']),
            new HlsVariant(['name' => '1080p', 'size' => 1080, 'maxrate' => '5350k', 'bufsize' => '7500k', 'audioBitrate' => '128k']),
            new HlsVariant(['name' => '720p', 'size' => 720, 'maxrate' => '2996k', 'bufsize' => '4200k', 'audioBitrate' => '128k']),
            new HlsVariant(['name' => '480p', 'size' => 480, 'maxrate' => '1498k', 'bufsize' => '2100k', 'audioBitrate' => '128k']),
            new HlsVariant(['name' => '360p', 'size' => 360, 'maxrate' => '856k', 'bufsize' => '1200k', 'audioBitrate' => '128k']),
            new HlsVariant(['name' => '240p', 'size' => 240, 'maxrate' => '642k', 'bufsize' => '900k', 'audioBitrate' => '128k']),
        ]);
    }

    /**
     * @return Collection<int, string>
     */
    public function audioBitrates(): Collection
    {
        return $this
            ->toBase()
            ->map(fn ($variant) => $variant->audioBitrate->format());
    }

    public function supportedBy(Dimension $source): static
    {
        return $this
            ->filter(fn ($variant) => $variant->isSupportedBy($source))
            ->values();
    }

    /**
     * The key of the variant whose size is the closest to the given one.
     */
    public function getKeyClosestTo(int|float $size): ?int
    {
        return array_key_first(
            $this->sortBy(fn ($variant) => abs($variant->size - $size))->all()
        );
    }

    public function setMaxBitrate(null|string|float|int|Bitrate $value): static
    {
        $value = Bitrate::parse($value);

        if ($value === null || $value->isZero()) {
            return $this;
        }

        return $this->map(fn ($variant) => new HlsVariant([
            ...$variant->toArray(),
            'maxrate' => $maxrate = $variant->maxrate->max($value),
            'bufsize' => $variant->bufsize->max($maxrate->value * 1.5),
        ]));

    }

    public function setAudioBitrate(string|float|int|Bitrate $value): static
    {
        return $this->map(fn ($variant) => new HlsVariant([
            ...$variant->toArray(),
            'audioBitrate' => $value,
        ]));
    }

    public function setMaxAudioBitrate(null|string|float|int|Bitrate $value): static
    {
        $value = Bitrate::parse($value);

        if ($value === null || $value->isZero()) {
            return $this;
        }

        return $this->map(fn ($variant) => new HlsVariant([
            ...$variant->toArray(),
            'audioBitrate' => $variant->audioBitrate->max($value),
        ]));

    }
}
