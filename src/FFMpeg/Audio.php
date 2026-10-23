<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg;

class Audio extends FFMpeg
{
    /**
     * @param  string  $bitrate
     *                           - 32k   : Very low quality, speech/voice only, smallest file size.
     *                           - 48k   : Low quality, speech with some music.
     *                           - 64k   : Medium quality, low-quality music or streaming voice.
     *                           - 96k   : Good quality, general music, small files.
     *                           - 128k  : Standard quality, good for most uses (default for MP3).
     *                           - 192k  : High quality, detailed music.
     *                           - 256k+ : Very high quality, archival purposes (large files).
     */
    public function mp3(
        string $input,
        string $output,
        string $bitrate = '128k',
    ): string {
        return $this->ffmpeg([
            '-i', $input,
            '-vn',
            '-acodec', 'libmp3lame',
            '-b:a', $bitrate,
            $output,
        ]);
    }

    public function wav(
        string $input,
        string $output,
    ): string {
        return $this->ffmpeg([
            '-i', $input,
            '-vn',
            '-acodec', 'pcm_s16le',
            '-ar', '44100',
            '-ac', '2',
            $output,
        ]);
    }

    /**
     * @param  string  $bitrate
     *                           - 32k   : Very low quality, speech/voice only, smallest file size.
     *                           - 48k   : Low quality, speech with some music.
     *                           - 64k   : Medium quality, low-quality music or streaming voice.
     *                           - 96k   : Good quality, general music, small files.
     *                           - 128k  : Standard quality, good for most uses (default for MP3).
     *                           - 192k  : High quality, detailed music.
     *                           - 256k+ : Very high quality, archival purposes (large files).
     * @param  int  $channels
     *                         - 1 : Mono — single channel audio, smallest file size, good for voice.
     *                         - 2 : Stereo — two-channel audio, standard for music and video.
     *                         - 4 : Quad — four-channel audio (rare, surround setups).
     *                         - 6 : 5.1 Surround — six channels (home theater, cinema).
     *                         - 8 : 7.1 Surround — eight channels (high-end surround systems).
     */
    public function aac(
        string $input,
        string $output,
        string $bitrate = '128k',
        int $channels = 2,
    ): string {
        return $this->ffmpeg([
            '-i', $input,
            '-vn',
            '-c:a', 'aac',
            '-b:a', $bitrate,
            '-ac', (string) $channels,
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
