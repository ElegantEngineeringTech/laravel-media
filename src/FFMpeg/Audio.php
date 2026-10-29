<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg;

use Elegantly\Media\FFMpeg\Exceptions\AudioStreamNotFoundException;
use Elegantly\Media\FFMpeg\ValueObjects\Stream;

class Audio extends FFMpeg
{
    /**
     * The default audio stream ffmpeg can decode.
     *
     * @see FFMpeg::decodableStreams()
     */
    public function audioStream(string $input): Stream
    {
        $stream = $this->decodableStreams($input)->defaultAudio();

        if (! $stream) {
            throw AudioStreamNotFoundException::atPath($input);
        }

        return $stream;
    }

    /**
     * @param  string  $bitrate
     *                           - 32k   : Very low quality, speech/voice only, smallest file size.
     *                           - 48k   : Low quality, speech with some music.
     *                           - 64k   : Medium quality, low-quality music or streaming voice.
     *                           - 96k   : Good quality, general music, small files.
     *                           - 128k  : Standard quality, good for most uses (default, web streaming standard).
     *                           - 192k  : High quality, detailed music.
     *                           - 256k+ : Very high quality, archival purposes (large files).
     * @param  null|int  $channels
     *                              - null : Keep the source channel layout.
     *                              - 1    : Mono — single channel audio, smallest file size, good for voice.
     *                              - 2    : Stereo — two-channel audio (MP3 supports at most 2 channels).
     */
    public function mp3(
        string $input,
        string $output,
        string $bitrate = '128k',
        ?int $channels = null,
    ): string {
        $stream = $this->audioStream($input);

        return $this->ffmpeg([
            '-i', $input,
            '-map', "0:{$stream->index()}",
            '-acodec', 'libmp3lame',
            '-b:a', $bitrate,
            ...($channels ? ['-ac', (string) $channels] : []),
            $output,
        ]);
    }

    /**
     * @param  null|int  $channels
     *                              - null : Keep the source channel layout.
     *                              - 1    : Mono — single channel audio, smallest file size, good for voice.
     *                              - 2    : Stereo — two-channel audio, standard for music and video.
     *                              - 4    : Quad — four-channel audio (rare, surround setups).
     *                              - 6    : 5.1 Surround — six channels (home theater, cinema).
     *                              - 8    : 7.1 Surround — eight channels (high-end surround systems).
     */
    public function wav(
        string $input,
        string $output,
        ?int $channels = null,
    ): string {
        $stream = $this->audioStream($input);

        return $this->ffmpeg([
            '-i', $input,
            '-map', "0:{$stream->index()}",
            '-acodec', 'pcm_s16le',
            '-ar', '44100',
            ...($channels ? ['-ac', (string) $channels] : []),
            $output,
        ]);
    }

    /**
     * @param  string  $bitrate
     *                           - 32k   : Very low quality, speech/voice only, smallest file size.
     *                           - 48k   : Low quality, speech with some music.
     *                           - 64k   : Medium quality, low-quality music or streaming voice.
     *                           - 96k   : Good quality, general music, small files.
     *                           - 128k  : Standard quality, good for most uses (default, web streaming standard).
     *                           - 192k  : High quality, detailed music.
     *                           - 256k+ : Very high quality, archival purposes (large files).
     * @param  null|int  $channels
     *                              - null : Keep the source channel layout.
     *                              - 1    : Mono — single channel audio, smallest file size, good for voice.
     *                              - 2    : Stereo — two-channel audio, standard for music and video.
     *                              - 4    : Quad — four-channel audio (rare, surround setups).
     *                              - 6    : 5.1 Surround — six channels (home theater, cinema).
     *                              - 8    : 7.1 Surround — eight channels (high-end surround systems).
     */
    public function aac(
        string $input,
        string $output,
        string $bitrate = '128k',
        ?int $channels = null,
    ): string {
        $stream = $this->audioStream($input);

        return $this->ffmpeg([
            '-i', $input,
            '-map', "0:{$stream->index()}",
            '-c:a', 'aac',
            '-b:a', $bitrate,
            ...($channels ? ['-ac', (string) $channels] : []),
            $output,
        ]);
    }

    /**
     * Use an .opus, .ogg or .webm output.
     *
     * @param  string  $bitrate
     *                           - 16k   : Low quality, speech/voice only, smallest file size.
     *                           - 24k   : Good quality speech.
     *                           - 32k   : Very good quality speech, low-quality music.
     *                           - 64k   : Good quality music, small files.
     *                           - 96k   : Very good quality, general music (default, matches AAC at 128k).
     *                           - 128k  : High quality, detailed music.
     *                           - 192k+ : Very high quality, archival purposes (large files).
     * @param  null|int  $channels
     *                              - null : Keep the source channel layout.
     *                              - 1    : Mono — single channel audio, smallest file size, good for voice.
     *                              - 2    : Stereo — two-channel audio, standard for music and video.
     *                              - 4    : Quad — four-channel audio (rare, surround setups).
     *                              - 6    : 5.1 Surround — six channels (home theater, cinema).
     *                              - 8    : 7.1 Surround — eight channels (high-end surround systems).
     */
    public function opus(
        string $input,
        string $output,
        string $bitrate = '96k',
        ?int $channels = null,
    ): string {
        $stream = $this->audioStream($input);

        return $this->ffmpeg([
            '-i', $input,
            '-map', "0:{$stream->index()}",
            '-c:a', 'libopus',
            '-b:a', $bitrate,
            ...($channels ? ['-ac', (string) $channels] : []),
            $output,
        ]);
    }

    public function save(
        string $input,
        string $output,
    ): string {
        $extension = pathinfo($output, PATHINFO_EXTENSION);

        return match ($extension) {
            'mp3' => $this->mp3($input, $output),
            default => $this->wav($input, $output),
        };
    }
}
