<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

class HlsVariant
{
    public readonly string $name;

    public readonly int|float $height;

    public readonly Bitrate $bitrate;

    public readonly Bitrate $maxrate;

    public readonly Bitrate $bufsize;

    public readonly Bitrate $audioBitrate;

    /**
     * @param  array{name: string, height: int|float, bitrate: int|float|string, maxrate: int|float|string, bufsize: int|float|string, audioBitrate: int|float|string}  $data
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
}
