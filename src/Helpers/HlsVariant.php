<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, string|int|float|Bitrate>
 */
class HlsVariant implements Arrayable
{
    public readonly string $name;

    public readonly int|float $height;

    public readonly Bitrate $bitrate;

    public readonly Bitrate $maxrate;

    public readonly Bitrate $bufsize;

    public readonly Bitrate $audioBitrate;

    /**
     * @param  array{name: string, height: int|float, bitrate: int|float|string|Bitrate, maxrate: int|float|string|Bitrate, bufsize: int|float|string|Bitrate, audioBitrate: int|float|string|Bitrate}  $data
     */
    final public function __construct(array $data)
    {
        $this->name = $data['name'];
        $this->height = $data['height'];
        $this->bitrate = Bitrate::parse($data['bitrate']);
        $this->maxrate = Bitrate::parse($data['maxrate']);
        $this->bufsize = Bitrate::parse($data['bufsize']);
        $this->audioBitrate = Bitrate::parse($data['audioBitrate']);
    }

    /**
     * The largest 16:9 dimension a rendition of this variant can have
     * (e.g. 1920x1080 for 1080p, or 1080x1920 when portrait).
     */
    public function maxDimension(bool $portrait = false): Dimension
    {
        $short = (int) $this->height;
        $long = (int) (2 * round($short * 16 / 9 / 2));

        return $portrait ? new Dimension($short, $long) : new Dimension($long, $short);
    }

    /**
     * @return array{name: string, height: int|float, bitrate: Bitrate, maxrate: Bitrate, bufsize: Bitrate, audioBitrate: Bitrate}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'height' => $this->height,
            'bitrate' => $this->bitrate,
            'maxrate' => $this->maxrate,
            'bufsize' => $this->bufsize,
            'audioBitrate' => $this->audioBitrate,
        ];
    }
}
