<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, null|int|Bitrate>
 */
class VideoRendition implements Arrayable
{
    public readonly int $size;

    /**
     * The frame rate cap, null to keep the source frame rate.
     */
    public readonly ?int $fps;

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
     * @param  array{ size: int, fps?: ?int, maxrate: int|float|string|Bitrate, bufsize: int|float|string|Bitrate, audioBitrate: int|float|string|Bitrate}  $data
     */
    final public function __construct(array $data)
    {
        $this->size = $data['size'];
        $this->fps = $data['fps'] ?? null;
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
        $short = $this->size;
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
            return $this;
        }

        return new static([
            ...$this->toArray(),
            'maxrate' => $maxrate = $this->maxrate->max($value),
            'bufsize' => $this->bufsize->max($maxrate->value * 1.5),
        ]);
    }

    public function setMaxFps(?int $value): static
    {
        if ($value === null) {
            return $this;
        }

        return new static([
            ...$this->toArray(),
            'fps' => $this->fps === null ? $value : min($this->fps, $value),
        ]);
    }

    public function setAudioBitrate(string|float|int|Bitrate $value): static
    {
        return new static([
            ...$this->toArray(),
            'audioBitrate' => $value,
        ]);
    }

    public function setMaxAudioBitrate(null|string|float|int|Bitrate $value): static
    {
        $value = Bitrate::parse($value);

        if ($value === null || $value->isZero()) {
            return $this;
        }

        return new static([
            ...$this->toArray(),
            'audioBitrate' => $this->audioBitrate->max($value),
        ]);
    }

    /**
     * @return array{ size: int, fps: ?int, maxrate: Bitrate, bufsize: Bitrate, audioBitrate: Bitrate}
     */
    public function toArray(): array
    {
        return [
            'size' => $this->size,
            'fps' => $this->fps,
            'maxrate' => $this->maxrate,
            'bufsize' => $this->bufsize,
            'audioBitrate' => $this->audioBitrate,
        ];
    }
}
