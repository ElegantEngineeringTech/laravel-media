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
            new HlsVariant(['name' => '2160p', 'height' => 2160, 'bitrate' => '25000k', 'maxrate' => '26750k', 'bufsize' => '37500k', 'audioBitrate' => '256k']),
            new HlsVariant(['name' => '1440p', 'height' => 1440, 'bitrate' => '8000k', 'maxrate' => '8560k', 'bufsize' => '12000k', 'audioBitrate' => '256k']),
            new HlsVariant(['name' => '1080p', 'height' => 1080, 'bitrate' => '5000k', 'maxrate' => '5350k', 'bufsize' => '7500k', 'audioBitrate' => '256k']),
            new HlsVariant(['name' => '720p', 'height' => 720, 'bitrate' => '2800k', 'maxrate' => '2996k', 'bufsize' => '4200k', 'audioBitrate' => '256k']),
            new HlsVariant(['name' => '480p', 'height' => 480, 'bitrate' => '1400k', 'maxrate' => '1498k', 'bufsize' => '2100k', 'audioBitrate' => '192k']),
            new HlsVariant(['name' => '360p', 'height' => 360, 'bitrate' => '800k', 'maxrate' => '856k', 'bufsize' => '1200k', 'audioBitrate' => '128k']),
            new HlsVariant(['name' => '240p', 'height' => 240, 'bitrate' => '600k', 'maxrate' => '642k', 'bufsize' => '900k', 'audioBitrate' => '96k']),
        ]);
    }

    public function setMaxBitrate(null|string|float|int|Bitrate $value): static
    {
        if ($value === null) {
            return $this;
        }

        return $this->map(fn ($variant) => new HlsVariant([
            ...$variant->toArray(),
            'bitrate' => $bitrate = $variant->bitrate->max($value),
            'maxrate' => $variant->maxrate->max($value),
            'bufsize' => $variant->bufsize->max($bitrate->value * 1.5),
        ]));

    }

    public function setMaxAudioBitrate(null|string|float|int|Bitrate $value): static
    {
        if ($value === null) {
            return $this;
        }

        return $this->map(fn ($variant) => new HlsVariant([
            ...$variant->toArray(),
            'audioBitrate' => $variant->audioBitrate->max($value),
        ]));

    }
}
