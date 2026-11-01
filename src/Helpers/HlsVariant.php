<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A variant of an HLS stream: the video at one resolution, with the audio muxed in,
 * split into segments listed by its own media playlist.
 *
 * @implements Arrayable<string, null|int|float|string>
 */
class HlsVariant implements Arrayable
{
    /**
     * @param  string  $name  The name of the variant (e.g. "720p").
     * @param  string  $playlist  The file name of the media playlist (e.g. "720p_playlist.m3u8").
     * @param  string  $init  The file name of the initialization segment, holding the codecs configuration.
     * @param  list<array{ file: string, duration: float, size: int }>  $segments  The segments in playback order,
     *                                                                             with their duration in seconds and their size in bytes.
     * @param  int  $targetDuration  The maximum duration of a segment in seconds, as declared by the media playlist.
     * @param  Dimension  $dimension  The size of the video.
     * @param  null|float  $frameRate  The frame rate of the video.
     * @param  string[]  $codecs  The codecs of the streams in the RFC 6381 format (e.g. "avc1.64001f", "mp4a.40.2").
     */
    public function __construct(
        public readonly string $name,
        public readonly string $playlist,
        public readonly string $init,
        public readonly array $segments,
        public readonly int $targetDuration,
        public readonly Dimension $dimension,
        public readonly ?float $frameRate,
        public readonly array $codecs,
    ) {
        //
    }

    /**
     * The peak segment bit rate in bits per second, as defined by the HLS specification:
     * the highest bit rate of any run of consecutive segments lasting between 0.5 and 1.5 times the target duration.
     * Variants too short for such a run peak at their average bit rate.
     */
    public function bandwidth(): int
    {
        $peak = 0.0;
        $count = count($this->segments);

        for ($first = 0; $first < $count; $first++) {

            $size = 0;
            $duration = 0.0;

            for ($last = $first; $last < $count; $last++) {

                $size += $this->segments[$last]['size'];
                $duration += $this->segments[$last]['duration'];

                if ($duration > 1.5 * $this->targetDuration) {
                    break;
                }

                if ($duration >= 0.5 * $this->targetDuration) {
                    $peak = max($peak, $size * 8 / $duration);
                }
            }
        }

        return $peak > 0 ? (int) ceil($peak) : $this->averageBandwidth();
    }

    /**
     * The bit rate averaged over all the segments, in bits per second.
     */
    public function averageBandwidth(): int
    {
        $duration = array_sum(array_column($this->segments, 'duration'));

        if ($duration <= 0) {
            return 0;
        }

        return (int) ceil(array_sum(array_column($this->segments, 'size')) * 8 / $duration);
    }

    /**
     * The media playlist, the initialization segment and the segments, relative to the master playlist.
     *
     * @return string[]
     */
    public function files(): array
    {
        return [
            $this->playlist,
            $this->init,
            ...array_column($this->segments, 'file'),
        ];
    }

    /**
     * @return array{ name: string, playlist: string, width: int, height: int, frameRate: null|float, bandwidth: int, averageBandwidth: int, codecs: string }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'playlist' => $this->playlist,
            'width' => $this->dimension->width,
            'height' => $this->dimension->height,
            'frameRate' => $this->frameRate,
            'bandwidth' => $this->bandwidth(),
            'averageBandwidth' => $this->averageBandwidth(),
            'codecs' => implode(',', $this->codecs),
        ];
    }
}
