<?php

declare(strict_types=1);

namespace Elegantly\Media\Converters\Audio;

use Elegantly\Media\Converters\MediaConverter;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\FFMpeg\FFMpeg;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Illuminate\Contracts\Filesystem\Filesystem;
use Spatie\TemporaryDirectory\TemporaryDirectory as SpatieTemporaryDirectory;

class MediaAacConverter extends MediaConverter
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
     * @param  int  $channels
     *                         - 1 : Mono — single channel audio, smallest file size, good for voice.
     *                         - 2 : Stereo — two-channel audio, standard for music and video.
     *                         - 4 : Quad — four-channel audio (rare, surround setups).
     *                         - 6 : 5.1 Surround — six channels (home theater, cinema).
     *                         - 8 : 7.1 Surround — eight channels (high-end surround systems).
     */
    public function __construct(
        public readonly Media $media,
        public string $filename,
        public string $bitrate = '64k',
        public int $channels = 2,
    ) {}

    public function shouldExecute(Media $media, ?MediaConversion $parent): bool
    {
        $source = $parent ?? $media;

        return in_array($source->type, [MediaType::Audio, MediaType::Video]);
    }

    public function convert(
        Media $media,
        ?MediaConversion $parent,
        ?string $file,
        Filesystem $filesystem,
        SpatieTemporaryDirectory $temporaryDirectory
    ): ?MediaConversion {

        if (! $file) {
            return null;
        }

        $input = $filesystem->path($file);
        $output = $filesystem->path($this->filename);

        $ffmpeg = new FFMpeg;

        if (! $ffmpeg->video()->hasAudio($input)) {
            return $this->skipConversion();
        }

        $ffmpeg->audio()->aac(
            input: $input,
            output: $output,
            bitrate: $this->bitrate,
            channels: $this->channels,
        );

        return $media->addConversion(
            file: $output,
            conversionName: $this->conversion,
            parent: $parent,
        );

    }
}
