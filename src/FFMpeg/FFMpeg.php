<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg;

use Elegantly\Media\FFMpeg\Exceptions\FFMpegException;
use Elegantly\Media\FFMpeg\ValueObjects\Metadata;
use Elegantly\Media\FFMpeg\ValueObjects\StreamCollection;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class FFMpeg
{
    public string $ffmpeg;

    public string $ffprobe;

    public ?string $logChannel = null;

    /**
     * The timeout in seconds of the processes, null to disable it.
     */
    public ?float $timeout = null;

    /**
     * The codecs ffmpeg can decode, detected on first use.
     *
     * @var null|string[]
     */
    protected ?array $decodableCodecs = null;

    final public function __construct(
        ?string $ffmpeg = null,
        ?string $ffprobe = null,
        ?string $logChannel = null,
        ?float $timeout = null,
    ) {
        // @phpstan-ignore-next-line
        $this->ffmpeg = $ffmpeg ?? config('media.ffmpeg.ffmpeg_binaries');

        // @phpstan-ignore-next-line
        $this->ffprobe = $ffprobe ?? config('media.ffmpeg.ffprobe_binaries');

        // @phpstan-ignore-next-line
        $this->logChannel = $logChannel ?: config('media.ffmpeg.log_channel');

        // @phpstan-ignore-next-line
        $this->timeout = $timeout ?? config('media.ffmpeg.timeout');
    }

    public static function make(
        ?string $ffmpeg = null,
        ?string $ffprobe = null,
        ?string $logChannel = null,
        ?float $timeout = null,
    ): static {
        return new static($ffmpeg, $ffprobe, $logChannel, $timeout);
    }

    public function video(): Video
    {
        return (new Video($this->ffmpeg, $this->ffprobe, $this->logChannel, $this->timeout))
            ->setDecodableCodecs($this->decodableCodecs);
    }

    public function audio(): Audio
    {
        return (new Audio($this->ffmpeg, $this->ffprobe, $this->logChannel, $this->timeout))
            ->setDecodableCodecs($this->decodableCodecs);
    }

    /**
     * Probe the media format and streams with ffprobe.
     */
    public function metadata(string $input): Metadata
    {
        $output = $this->ffprobe([
            '-v', 'error',
            '-show_format',
            '-show_streams',
            '-print_format', 'json',
            $input,
        ]);

        return Metadata::fromJson($output);
    }

    /**
     * The streams ffmpeg can decode.
     *
     * Notes:
     * - ffmpeg fails on a stream it can't decode instead of skipping it, whether the stream is mapped
     *   or picked by its own selection (the default audio stream with the most channels).
     * - Recent iPhones record spatial audio in a default 4.0 APAC track that ffmpeg can't decode,
     *   along with a stereo AAC track.
     */
    public function decodableStreams(string $input): StreamCollection
    {
        return $this->metadata($input)->streams()->decodable($this->decodableCodecs());
    }

    /**
     * The names of the codecs ffmpeg can decode, as reported in the `codec_name` of ffprobe streams.
     *
     * @return string[]
     */
    public function decodableCodecs(): array
    {
        if ($this->decodableCodecs === null) {
            $output = $this->ffmpeg(['-hide_banner', '-codecs']);

            // Codecs are listed as " DEA.L. aac  AAC (Advanced Audio Coding)", where D means decoding is supported
            preg_match_all('/^ D[.E][VASDT][.I][.L][.S] (\S+)/m', $output, $matches);

            $this->decodableCodecs = $matches[1];
        }

        return $this->decodableCodecs;
    }

    /**
     * @param  null|string[]  $codecs
     */
    public function setDecodableCodecs(?array $codecs): static
    {
        $this->decodableCodecs = $codecs;

        return $this;
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
        $process->setTimeout($this->timeout);
        $process->run();

        if ($process->isSuccessful()) {
            return $process->getOutput();
        }

        $code = $process->getExitCode() ?? 1;
        $error = $process->getErrorOutput();

        throw FFMpegException::executionFailed($binary, $code, implode(' ', $command), $error);
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
     * Detect whether the media contains at least one audio stream.
     *
     * Notes:
     * - This detects presence of audio streams, not whether audio is decodable or non-empty.
     * - Commonly used for container inspection (MP4, MKV, MP3, etc.).
     */
    public function hasAudio(string $input): bool
    {
        return $this->metadata($input)->streams()->hasAudio();
    }

    /**
     * Detect whether the media contains a real video stream, ignoring embedded cover art.
     *
     * @see Stream::isVideo()
     */
    public function hasVideo(string $input): bool
    {
        return $this->metadata($input)->streams()->hasVideo();
    }

    /**
     * Detect whether the media contains embedded artwork (cover image).
     *
     * Notes:
     * - Common in MP3, M4A, FLAC containers with embedded album art.
     */
    public function hasArtwork(string $input): bool
    {
        return $this->metadata($input)->streams()->hasArtwork();
    }

    /**
     * @return float The duration in milliseconds
     */
    public function duration(string $input): float
    {
        if ($duration = $this->metadata($input)->duration()) {
            return $duration;
        }

        return 0.0;
    }
}
