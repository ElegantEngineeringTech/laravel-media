<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg;

use Elegantly\Media\FFMpeg\Exceptions\VideoStreamNotFoundException;

class Video extends FFMpeg
{
    public function metadata(string $input): array
    {
        [$code, $output] = $this->ffprobe("-v error -select_streams v:0 -show_format -show_streams -print_format json {$input}");

        $metadata = json_decode(implode('', $output), true);

        // @phpstan-ignore-next-line
        return is_array($metadata) ? $metadata : [];
    }

    public function hasAudio(string $input): bool
    {
        [$code, $output] = $this->ffprobe("-v error -select_streams a -show_entries stream=index -of csv=p=0 {$input}");

        return ! empty($output);
    }

    public function hasVideo(string $input): bool
    {
        [$code, $output] = $this->ffprobe("-v error -select_streams v -show_entries stream=index -of csv=p=0 {$input}");

        return ! empty($output);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public function dimensions(string $input): array
    {
        $metadata = $this->metadata($input);

        if ($stream = $metadata['streams'][0] ?? null) {

            // @phpstan-ignore-next-line
            $width = (int) data_get($stream, 'width');
            // @phpstan-ignore-next-line
            $height = (int) data_get($stream, 'height');
            // @phpstan-ignore-next-line
            $rotation = (int) data_get($stream, 'side_data_list.0.rotation', 0);

            return [$width, $height, $rotation];
        }

        throw VideoStreamNotFoundException::atPath($input);
    }

    /**
     * @return float The duration in milliseconds
     */
    public function duration(string $input): float
    {
        $metadata = $this->metadata($input);

        /** @var float|int|string */
        $duration = data_get($metadata, 'format.duration');

        $duration = (float) $duration;

        return $duration * 1_000;
    }

    /**
     * @param  int|float|string  $timecode  in seconds (SS.xxx) or formatted (HH:MM:SS.xx)
     * @return array{0: int, 1: string[]}
     */
    public function frame(
        string $input,
        string $output,
        int|float|string $timecode = '00:00:00',
        ?int $width = null,
        ?int $height = null,
    ): array {
        if ($scale = $this->getScale($width, $height)) {
            return $this->ffmpeg("-ss {$timecode} -i {$input} -vframes 1 -vf {$scale} {$output}");
        }

        return $this->ffmpeg("-ss {$timecode} -i {$input} -vframes 1 {$output}");
    }

    /**
     * Transcodes a video file to MP4 (H.264/AAC) using FFmpeg.
     * This method optimizes the video for web playback by applying a YUV420p pixel format and the +faststart flag.
     *
     * @param  string  $input  The absolute path to the source video file.
     * @param  string  $output  The absolute path where the output MP4 should be saved.
     * @param  int|null  $width  The target width in pixels. If null, original width is kept.
     * @param  int|null  $height  The target height in pixels. If null, original height is kept.
     * @param  int|null  $fps  Target frames per second.
     * @param  int  $crf  Constant Rate Factor (0–51).
     *                    18 is visually lossless;
     *                    23 is default;
     *                    higher is lower quality.
     * @param  string  $preset  The compression speed (e.g., 'ultrafast', 'medium', 'veryslow').
     *                          Slower presets result in better compression/smaller files.
     * @return array{0: int, 1: string[]} Returns an array where index 0 is the exit code and index 1 is the output log.
     */
    public function mp4(
        string $input,
        string $output,
        ?int $width = null,
        ?int $height = null,
        ?int $fps = null,
        int $crf = 18,
        string $preset = 'veryslow'
    ): array {

        $filters = implode(',', [
            $this->getScale($width, $height),
            $fps ? "fps={$fps}" : 'null',
        ]);

        return $this->ffmpeg("-i {$input} -vf \"{$filters}\" -c:v libx264 -crf {$crf} -preset {$preset} -pix_fmt yuv420p -c:a aac -b:a 128k -movflags +faststart {$output}");
    }

    /**
     * Transcodes a video file to WebM (VP9/Opus) using FFmpeg.
     *
     * This method uses the libvpx-vp9 encoder in Constrained Quality (CQ) mode.
     * It enables multi-threading and modern audio compression (Opus) for superior web performance.
     *
     * @param  string  $input  The absolute path to the source video file.
     * @param  string  $output  The absolute path for the output .webm file.
     * @param  int|null  $width  Target width in pixels. Auto-scales if null.
     * @param  int|null  $height  Target height in pixels. Auto-scales if null.
     * @param  int|null  $fps  Target frames per second. Keeps original if null.
     * @param  int  $crf  Constant Rate Factor (0–63).
     *                    Lower is higher quality.
     *                    31–34 is recommended for 1080p;
     *                    15-18 for high-quality archiving.
     * @param  string  $deadline  The quality/speed tradeoff: 'realtime', 'good', or 'best'.
     *                            'good' is the recommended balance.
     * @param  int  $cpuUsed  Encoding efficiency (0–8). Higher values speed up encoding at a slight cost to quality.
     * @return array{0: int, 1: string[]} Returns an array where index 0 is the exit code and index 1 is the output log.
     */
    public function webm(
        string $input,
        string $output,
        ?int $width = null,
        ?int $height = null,
        ?int $fps = null,
        int $crf = 32,
        string $deadline = 'good',
        int $cpuUsed = 3,
    ): array {

        $filters = implode(',', [
            $this->getScale($width, $height),
            $fps ? "fps={$fps}" : 'null',
        ]);

        return $this->ffmpeg("-i {$input} -vf \"{$filters}\" -c:v libvpx-vp9 -crf {$crf} -b:v 0 -row-mt 1 -deadline {$deadline} -cpu-used {$cpuUsed} -c:a libopus -b:a 96k -movflags +faststart {$output}");
    }
}
