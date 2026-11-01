<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Frames of a video tiled into one or several sprite sheets.
 *
 * Frame n shows the video at n × interval seconds and stands for the time range until the next frame.
 * Frames fill the sheets row by row, from left to right. Every sheet has the same grid,
 * the tiles left empty in the last one being black.
 *
 * @implements Arrayable<string, int|float|string|string[]>
 */
class Spritesheet implements Arrayable
{
    /**
     * @param  string[]  $sheets  The file names of the sheets, in order.
     * @param  string  $vtt  The file name of the WebVTT file mapping each time range to its frame.
     * @param  int  $frames  The number of frames.
     * @param  float  $interval  The time between two frames, in seconds.
     * @param  int  $columns  The number of frames per row.
     * @param  int  $rows  The number of rows per sheet.
     * @param  Dimension  $dimension  The size of a frame.
     */
    public function __construct(
        public readonly array $sheets,
        public readonly string $vtt,
        public readonly int $frames,
        public readonly float $interval,
        public readonly int $columns,
        public readonly int $rows,
        public readonly Dimension $dimension,
    ) {
        //
    }

    /**
     * The sheet holding a frame, and the position of the frame in it.
     *
     * @return array{ sheet: string, x: int, y: int }
     */
    public function position(int $frame): array
    {
        $tiles = $this->columns * $this->rows;
        $tile = $frame % $tiles;

        return [
            'sheet' => $this->sheets[intdiv($frame, $tiles)],
            'x' => ($tile % $this->columns) * $this->dimension->width,
            'y' => intdiv($tile, $this->columns) * $this->dimension->height,
        ];
    }

    /**
     * The WebVTT file used by video players for timeline previews.
     * Each cue references its frame with a media fragment (e.g. "sprites.jpg#xywh=160,0,160,90"),
     * the sheet file name being relative to the WebVTT file.
     *
     * @param  null|float  $duration  The duration of the video in seconds, the last frame then standing for the time range until the end.
     */
    public function toVtt(?float $duration = null): string
    {
        $cues = [];

        for ($frame = 0; $frame < $this->frames; $frame++) {

            $start = $frame * $this->interval;

            $end = $frame === $this->frames - 1 && $duration !== null && $duration > $start
                ? $duration
                : ($frame + 1) * $this->interval;

            ['sheet' => $sheet, 'x' => $x, 'y' => $y] = $this->position($frame);

            $cues[] = static::timestamp($start).' --> '.static::timestamp($end)."\n"
                ."{$sheet}#xywh={$x},{$y},{$this->dimension->width},{$this->dimension->height}";
        }

        return "WEBVTT\n\n".implode("\n\n", $cues)."\n";
    }

    /**
     * Format seconds as a WebVTT timestamp (HH:MM:SS.mmm).
     */
    protected static function timestamp(float $seconds): string
    {
        $milliseconds = (int) round($seconds * 1_000);

        return sprintf(
            '%02d:%02d:%02d.%03d',
            intdiv($milliseconds, 3_600_000),
            intdiv($milliseconds, 60_000) % 60,
            intdiv($milliseconds, 1_000) % 60,
            $milliseconds % 1_000,
        );
    }

    /**
     * @return array{ frames: int, interval: float, columns: int, rows: int, width: int, height: int, sheets: string[], vtt: string }
     */
    public function toArray(): array
    {
        return [
            'frames' => $this->frames,
            'interval' => $this->interval,
            'columns' => $this->columns,
            'rows' => $this->rows,
            'width' => $this->dimension->width,
            'height' => $this->dimension->height,
            'sheets' => $this->sheets,
            'vtt' => $this->vtt,
        ];
    }
}
