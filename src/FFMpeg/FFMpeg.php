<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg;

use Exception;
use Illuminate\Support\Facades\Log;

class FFMpeg
{
    public string $ffmpeg;

    public string $ffprobe;

    public ?string $logChannel = null;

    final public function __construct(
        ?string $ffmpeg = null,
        ?string $ffprobe = null,
        ?string $logChannel = null,
    ) {
        // @phpstan-ignore-next-line
        $this->ffmpeg = $ffmpeg ?? config('media.ffmpeg.ffmpeg_binaries') ?? config('laravel-ffmpeg.ffmpeg.binaries');

        // @phpstan-ignore-next-line
        $this->ffprobe = $ffprobe ?? config('media.ffprobe.ffprobe_binaries') ?? config('laravel-ffmpeg.ffprobe.binaries');

        // @phpstan-ignore-next-line
        $this->logChannel = $logChannel ?: config('media.ffmpeg.log_channel');
    }

    public static function make(
        ?string $ffmpeg = null,
        ?string $ffprobe = null,
        ?string $logChannel = null,
    ): static {
        return new static($ffmpeg, $ffprobe, $logChannel);
    }

    public function video(): Video
    {
        return new Video($this->ffmpeg, $this->ffprobe);
    }

    /**
     * @return array{0: int, 1: string[]}
     */
    protected function execute(string $command, bool $throw = true): array
    {

        if ($this->logChannel) {
            Log::channel($this->logChannel)->info("ffmpeg: {$command}");
        }

        exec("{$command} 2>&1", $output, $code);

        if ($throw && $code !== 0) {
            throw new Exception(
                "Error Executing ffmpeg: {$command}",
                500,
                new Exception(implode("\n", $output), $code)
            );
        }

        return [$code, $output];
    }

    /**
     * @return array{0: int, 1: string[]}
     */
    public function ffmpeg(string $command): array
    {
        return $this->execute("{$this->ffmpeg} {$command}");
    }

    /**
     * @return array{0: int, 1: string[]}
     */
    public function ffprobe(string $command): array
    {
        return $this->execute("{$this->ffprobe} {$command}");
    }
}
