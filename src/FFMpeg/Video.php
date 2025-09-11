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

        $duration = (float) data_get($metadata, 'format.duration');

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
     * @return array{0: int, 1: string[]}
     */
    public function mp4(
        string $input,
        string $output,
        ?int $width = null,
        ?int $height = null,
        int $crf = 18,
        string $preset = 'veryslow'
    ): array {
        if ($scale = $this->getScale($width, $height)) {
            return $this->ffmpeg("-i {$input} -vf {$scale} -c:v libx264 -crf {$crf} -preset {$preset} -pix_fmt yuv420p -c:a aac -b:a 128k -movflags +faststart {$output}");
        }

        return $this->ffmpeg("-i {$input} -vf -c:v libx264 -crf {$crf} -preset {$preset} -pix_fmt yuv420p -c:a aac -b:a 128k -movflags +faststart {$output}");
    }

    /**
     * @return array{0: int, 1: string[]}
     */
    public function webm(
        string $input,
        string $output,
        ?int $width = null,
        ?int $height = null,
        int $crf = 32,
        string $deadline = 'good',
        int $cpuUsed = 3,
    ): array {
        if ($scale = $this->getScale($width, $height)) {
            return $this->ffmpeg("-i {$input} -vf {$scale} -c:v libvpx-vp9 -crf {$crf} -b:v 0 -row-mt 1 -deadline {$deadline} -cpu-used {$cpuUsed} -c:a libopus -b:a 96k -movflags +faststart {$output}");
        }

        return $this->ffmpeg("-i {$input} -c:v libvpx-vp9 -crf {$crf} -b:v 0 -row-mt 1 -deadline {$deadline} -cpu-used {$cpuUsed} -c:a libopus -b:a 96k -movflags +faststart {$output}");
    }
}
