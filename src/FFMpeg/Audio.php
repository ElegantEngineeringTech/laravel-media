<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg;

class Audio extends FFMpeg
{
    /**
     * @return array{0: int, 1: string[]}
     */
    public function mp3(
        string $input,
        string $output,
    ): array {
        return $this->ffmpeg("-i {$input} -vn -acodec libmp3lame -b:a 128k {$output}");
    }

    /**
     * @return array{0: int, 1: string[]}
     */
    public function wav(
        string $input,
        string $output,
    ): array {
        return $this->ffmpeg("-i {$input} -vn -acodec pcm_s16le -ar 44100 -ac 2 {$output}");
    }

    /**
     * @return array{0: int, 1: string[]}
     */
    public function save(
        string $input,
        string $output,
    ): array {
        $extension = pathinfo($output, PATHINFO_EXTENSION);

        return match ($extension) {
            'mp3' => $this->mp3($input, $output),
            default => $this->wav($input, $output),
        };
    }
}
