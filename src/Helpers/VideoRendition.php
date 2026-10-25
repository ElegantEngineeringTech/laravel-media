<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, string|int|float|Bitrate>
 */
class VideoRendition implements Arrayable
{
    public readonly int|float $size;

    /**
     * The bitrate ceiling of the capped CRF encode.
     */
    public readonly Bitrate $maxrate;

    /**
     * The rate control buffer, defining over how long the bitrate is averaged before being capped at maxrate.
     */
    public readonly Bitrate $bufsize;

    public readonly Bitrate $audioBitrate;

    /**
     * @param  array{ size: int|float, maxrate: int|float|string|Bitrate, bufsize: int|float|string|Bitrate, audioBitrate: int|float|string|Bitrate}  $data
     */
    final public function __construct(array $data)
    {
        $this->size = $data['size'];
        $this->maxrate = Bitrate::parse($data['maxrate']);
        $this->bufsize = Bitrate::parse($data['bufsize']);
        $this->audioBitrate = Bitrate::parse($data['audioBitrate']);
    }

    /**
     * The largest 16:9 dimension this rendition can have
     * (e.g. 1920x1080 for 1080p, or 1080x1920 when portrait).
     */
    public function dimension(bool $portrait = false): Dimension
    {
        $short = (int) $this->size;
        $long = (int) (2 * round($short * 16 / 9 / 2));

        return $portrait ? new Dimension($short, $long) : new Dimension($long, $short);
    }

    public function isSupportedBy(Dimension $source): bool
    {
        $dimension = $this->dimension($source->isPortrait());

        return $dimension->width <= $source->width
            || $dimension->height <= $source->height;
    }

    public function setMaxBitrate(null|string|float|int|Bitrate $value): static
    {
        $value = Bitrate::parse($value);

        if ($value === null || $value->isZero()) {
            return new static($this->toArray());
        }

        return new static([
            ...$this->toArray(),
            'maxrate' => $maxrate = $this->maxrate->max($value),
            'bufsize' => $this->bufsize->max($maxrate->value * 1.5),
        ]);
    }

    public function setMaxAudioBitrate(null|string|float|int|Bitrate $value): static
    {
        $value = Bitrate::parse($value);

        if ($value === null || $value->isZero()) {
            return new static($this->toArray());
        }

        return new static([
            ...$this->toArray(),
            'audioBitrate' => $this->audioBitrate->max($value),
        ]);

    }

    /**
     * @return array{ size: int|float, maxrate: Bitrate, bufsize: Bitrate, audioBitrate: Bitrate}
     */
    public function toArray(): array
    {
        return [
            'size' => $this->size,
            'maxrate' => $this->maxrate,
            'bufsize' => $this->bufsize,
            'audioBitrate' => $this->audioBitrate,
        ];
    }
}
