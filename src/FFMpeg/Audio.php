<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg;

use Elegantly\Media\FFMpeg\Exceptions\AudioStreamNotFoundException;

class Audio extends FFMpeg
{
    public function metadata(string $input): array
    {
        [$code, $output] = $this->ffprobe("-v error -select_streams a:0 -show_format -show_streams -print_format json {$input}");

        $metadata = json_decode(implode('', $output), true);

        // @phpstan-ignore-next-line
        return is_array($metadata) ? $metadata : [];
    }

    /**
     * @return float The duration in milliseconds
     */
    public function duration(string $input): float
    {
        $metadata = $this->metadata($input);

        if ($stream = $metadata['streams'][0] ?? null) {

            // @phpstan-ignore-next-line
            $duration = (float) data_get($stream, 'duration');

            return $duration * 1_000;
        }

        throw AudioStreamNotFoundException::atPath($input);
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
