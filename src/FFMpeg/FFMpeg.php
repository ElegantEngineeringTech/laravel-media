<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg;

use Elegantly\Media\FFMpeg\Exceptions\FFMpegException;
use Elegantly\Media\FFMpeg\ValueObjects\Metadata;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

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
        $this->ffmpeg = $ffmpeg ?? config('media.ffmpeg.ffmpeg_binaries');

        // @phpstan-ignore-next-line
        $this->ffprobe = $ffprobe ?? config('media.ffmpeg.ffprobe_binaries');

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

    public function audio(): Audio
    {
        return new Audio($this->ffmpeg, $this->ffprobe);
    }

    public function metadata(string $input): Metadata
    {
        return $this->probe($input);
    }

    /**
     * Probe the media with ffprobe.
     *
     * @param  string|null  $select  An ffprobe stream specifier (e.g. "a:0"), every stream when null
     */
    protected function probe(
        string $input,
        ?string $select = null
    ): Metadata {
        $output = $this->ffprobe([
            '-v', 'error',
            ...($select ? ['-select_streams', $select] : []),
            '-show_format',
            '-show_streams',
            '-print_format', 'json',
            $input,
        ]);

        return Metadata::fromJson($output);
    }

    public function stripMetadata(
        string $input,
        string $output,
    ): string {
        return $this->ffmpeg([
            '-i', $input,
            '-map_metadata', '-1',
            '-c', 'copy',
            $output,
        ]);
    }

    /**
     * @param  string[]  $arguments
     * @return string Command output
     */
    protected function execute(string $binary, array $arguments): string
    {
        $command = array_merge([$binary], $arguments);

        if ($this->logChannel) {
            Log::channel($this->logChannel)->info('ffmpeg: '.implode(' ', $command));
        }

        $process = new Process($command);
        $process->setTimeout(null);
        $process->run();

        if ($process->isSuccessful()) {
            return $process->getOutput();
        }

        $code = $process->getExitCode() ?? 1;
        $error = $process->getErrorOutput();

        throw FFMpegException::executionFailed($code, implode(' ', $command), $error);
    }

    /**
     * @param  string[]  $arguments
     * @return string Command output
     */
    public function ffmpeg(array $arguments): string
    {
        return $this->execute($this->ffmpeg, $arguments);
    }

    /**
     * @param  string[]  $arguments
     * @return string Command output
     */
    public function ffprobe(array $arguments): string
    {
        return $this->execute($this->ffprobe, $arguments);
    }

    /**
     * The 'null' filter is an identity filter.
     */
    protected function getScale(?int $width = null, ?int $height = null): string
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

        return 'null';
    }

    /**
     * Detect whether the media contains at least one audio stream.
     *
     * Notes:
     * - This detects presence of audio streams, not whether audio is decodable or non-empty.
     * - Commonly used for container inspection (MP4, MKV, MP3, etc.).
     */
    public function hasAudio(string $input): bool
    {
        return $this->probe($input)->streams()->hasAudio();
    }

    /**
     * Detect whether the media contains a real video stream, ignoring embedded cover art.
     *
     * @see Stream::isVideo()
     */
    public function hasVideo(string $input): bool
    {
        return $this->probe($input)->streams()->hasVideo();
    }

    /**
     * Detect whether the media contains embedded artwork (cover image).
     *
     * Notes:
     * - Common in MP3, M4A, FLAC containers with embedded album art.
     */
    public function hasArtwork(string $input): bool
    {
        return $this->probe($input)->streams()->hasArtwork();
    }

    /**
     * @return float The duration in milliseconds
     */
    public function duration(string $input): float
    {
        $duration = $this->metadata($input)->duration();

        if ($duration !== null) {
            return $duration;
        }

        return 0.0;
    }
}
