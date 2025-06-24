<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg;

use Exception;
use Illuminate\Support\Facades\Log;

class FFMpeg
{
    public string $ffmpeg;

    public string $ffprobe;

    final public function __construct(
        ?string $ffmpeg = null,
        ?string $ffprobe = null,
    ) {
        // @phpstan-ignore-next-line
        $this->ffmpeg = $ffmpeg ?? config('media.ffmpeg.ffmpeg_binaries') ?? config('laravel-ffmpeg.ffmpeg.binaries');
        // @phpstan-ignore-next-line
        $this->ffprobe = $ffprobe ?? config('media.ffprobe.ffprobe_binaries') ?? config('laravel-ffmpeg.ffprobe.binaries');
    }

    public static function make(
        ?string $ffmpeg = null,
        ?string $ffprobe = null,
    ): static {
        return new static($ffmpeg, $ffprobe);
    }

    public function video(): Video
    {
        return new Video($this->ffmpeg, $this->ffprobe);
    }

    /**
     * @return array{0: int, 1: string[]}
     */
    protected function execute(string $command): array
    {
        // @phpstan-ignore-next-line
        if ($channel = config('media.ffmpeg.log_channel')) {
            Log::channel($channel)->info("ffmpeg: {$command}");
        }

        exec($command, $output, $code);

        return [$code, $output];
    }

    /**
     * @return array{0: int, 1: string[]}
     */
    public function ffmpeg(string $command): array
    {
        $cmd = "{$this->ffmpeg} {$command} 2>&1";

        [$code, $output] = $this->execute($cmd);

        if ($code !== 0) {
            throw new Exception("Error Executing ffmpeg: {$cmd}", $code);
        }

        return [$code, $output];
    }

    /**
     * @return array{0: int, 1: string[]}
     */
    public function ffprobe(string $command): array
    {
        $cmd = "{$this->ffprobe} {$command} 2>&1";

        [$code, $output] = $this->execute($cmd);

        if ($code !== 0) {
            throw new Exception("Error Executing ffprobe: {$cmd}", $code);
        }

        return [$code, $output];
    }
}
