<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg\ValueObjects;

use Elegantly\Media\FFMpeg\ValueObjects\Concerns\InteractsWithData;
use Elegantly\Media\Helpers\Bitrate;

/**
 * The output of `ffprobe -show_format -show_streams`.
 */
class Metadata
{
    use InteractsWithData;

    /**
     * @param  array<array-key, mixed>  $data
     */
    final public function __construct(
        public readonly array $data,
    ) {
        //
    }

    public static function fromJson(string $json): static
    {
        $data = json_decode($json, true);

        return new static(is_array($data) ? $data : []);
    }

    public function streams(): StreamCollection
    {
        $streams = $this->getArray('streams');

        $items = [];

        foreach ($streams as $stream) {
            if (is_array($stream)) {
                $items[] = new Stream($stream);
            }
        }

        return new StreamCollection($items);
    }

    public function formatName(): ?string
    {
        return $this->getString('format.format_name');
    }

    /**
     * @return float|null The duration in milliseconds
     */
    public function duration(): ?float
    {
        if ($duration = $this->getFloat('format.duration')) {
            return $duration * 1_000;
        }

        return null;
    }

    /**
     * @return int|null The size in bytes
     */
    public function size(): ?int
    {
        return $this->getInt('format.size');
    }

    /**
     * The overall bitrate of the container, all streams included.
     */
    public function bitrate(): ?Bitrate
    {
        $value = $this->getInt('format.bit_rate');

        return $value === null ? null : Bitrate::parse($value);
    }
}
