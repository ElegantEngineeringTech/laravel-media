<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg;

use Elegantly\Media\FFMpeg\Exceptions\FFMpegException;
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

    /**
     * @return array{
     *   streams?: array<int, array{
     *     index?: int,
     *     codec_type?: string,
     *     codec_name?: string,
     *     width?: int,
     *     height?: int,
     *     nb_frames?: string|int,
     *     disposition?: array<string, int>,
     *     tags?: array<string, mixed>
     *   }>,
     *   format?: array{
     *     filename?: string,
     *     nb_streams?: int,
     *     nb_programs?: int,
     *     format_name?: string,
     *     format_long_name?: string,
     *     duration?: string,
     *     size?: string,
     *     bit_rate?: string,
     *     tags?: array<string, mixed>
     *   }
     * }
     */
    public function metadata(string $input): array
    {
        $output = $this->ffprobe([
            '-v', 'error',
            '-show_format',
            '-show_streams',
            '-print_format', 'json',
            $input,
        ]);

        $metadata = json_decode($output, true);

        // @phpstan-ignore-next-line
        return is_array($metadata) ? $metadata : [];
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
     * Uses ffprobe `streams[].codec_type`.
     *
     * Notes:
     * - This detects presence of audio streams, not whether audio is decodable or non-empty.
     * - Commonly used for container inspection (MP4, MKV, MP3, etc.).
     */
    public function hasAudio(string $input): bool
    {
        $metadata = $this->metadata($input);

        $streams = $metadata['streams'] ?? [];

        foreach ($streams as $stream) {

            $codec_type = $stream['codec_type'] ?? null;

            if ($codec_type !== 'audio') {
                continue;
            }

            return true;

        }

        return false;
    }

    /**
     * Detect whether the media contains a real video stream.
     *
     * Uses ffprobe stream metadata:
     * - `codec_type`
     * - `disposition.attached_pic`
     * - `width` and `height`
     *
     * Notes:
     * - Filters out MP3/M4A embedded cover art.
     * - Attempts to avoid "empty" or metadata-only video streams.
     * - Does not rely on `nb_frames` because it is inconsistently present across formats,
     *   especially in WebM/Matroska containers.
     */
    public function hasVideo(string $input): bool
    {
        $metadata = $this->metadata($input);

        $streams = $metadata['streams'] ?? [];

        foreach ($streams as $stream) {

            $codec_type = $stream['codec_type'] ?? null;

            if ($codec_type !== 'video') {
                continue;
            }

            $disposition = $stream['disposition'] ?? [];
            $attached_pic = $disposition['attached_pic'] ?? 0;

            if ($attached_pic) {
                continue;
            }

            $width = $stream['width'] ?? null;
            $height = $stream['height'] ?? null;

            if (! $width || ! $height) {
                continue;
            }

            return true;

        }

        return false;
    }

    /**
     * Detect whether the media contains embedded artwork (cover image).
     *
     * Uses ffprobe stream metadata:
     * - `codec_type`
     * - `disposition.attached_pic`
     *
     * Notes:
     * - Common in MP3, M4A, FLAC containers with embedded album art.
     */
    public function hasArtwork(string $input): bool
    {
        $metadata = $this->metadata($input);

        $streams = $metadata['streams'] ?? [];

        foreach ($streams as $stream) {

            $codec_type = $stream['codec_type'] ?? null;

            if ($codec_type !== 'video') {
                continue;
            }

            $disposition = $stream['disposition'] ?? [];
            $attached_pic = $disposition['attached_pic'] ?? 0;

            if (! $attached_pic) {
                continue;
            }

            return true;

        }

        return false;
    }
}
