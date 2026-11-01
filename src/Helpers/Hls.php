<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

use Illuminate\Contracts\Support\Arrayable;

/**
 * An HLS stream: a master playlist listing the variants of a video, each with its own media playlist and segments.
 *
 * Players start with the first variant, then switch between them as the bandwidth allows.
 * Every segment starts with a keyframe, so it can be decoded on its own.
 *
 * @implements Arrayable<string, array<int, array<string, null|int|float|string>>>
 */
class Hls implements Arrayable
{
    /**
     * @param  HlsVariant[]  $variants  The variants, in the order of the master playlist.
     */
    public function __construct(
        public readonly array $variants,
    ) {
        //
    }

    /**
     * Every file the master playlist references, directly or through the media playlists, relative to it.
     *
     * @return string[]
     */
    public function files(): array
    {
        return array_merge(...array_map(
            fn ($variant) => $variant->files(),
            $this->variants
        ));
    }

    /**
     * The master playlist, with the variant attributes required by Apple devices:
     * the peak and average bandwidths, the codecs, the resolution and the frame rate.
     */
    public function toM3u8(): string
    {
        $variants = array_map(
            fn ($variant) => '#EXT-X-STREAM-INF:'.implode(',', array_filter([
                "BANDWIDTH={$variant->bandwidth()}",
                "AVERAGE-BANDWIDTH={$variant->averageBandwidth()}",
                'CODECS="'.implode(',', $variant->codecs).'"',
                "RESOLUTION={$variant->dimension->width}x{$variant->dimension->height}",
                $variant->frameRate ? 'FRAME-RATE='.number_format($variant->frameRate, 3, '.', '') : null,
            ]))."\n{$variant->playlist}",
            $this->variants
        );

        return "#EXTM3U\n#EXT-X-VERSION:7\n#EXT-X-INDEPENDENT-SEGMENTS\n\n".implode("\n\n", $variants)."\n";
    }

    /**
     * @return array{ variants: array<int, array{ name: string, playlist: string, width: int, height: int, frameRate: null|float, bandwidth: int, averageBandwidth: int, codecs: string }> }
     */
    public function toArray(): array
    {
        return [
            'variants' => array_map(fn ($variant) => $variant->toArray(), $this->variants),
        ];
    }
}
