<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg;

class Video extends FFMpeg
{
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

        return $this->ffmpeg("-ss {$timecode} -i {$input} -vframes 1 -vf {$output}");
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
    public function mp3(
        string $input,
        string $output,
    ): array {
        return $this->ffmpeg("-i {$input} -vn -acodec libmp3lame -b:a 128k {$output}");
    }

    protected function getScale(?int $width = null, ?int $height = null): ?string
    {
        if ($width && $height) {
            return "scale={$width}:{$height}";
        }

        if ($width) {
            return "scale={$width}:-2";
        }

        if ($height) {
            return "scale=-2:{$height}";
        }

        return null;
    }
}
