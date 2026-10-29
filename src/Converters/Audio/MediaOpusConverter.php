<?php

declare(strict_types=1);

namespace Elegantly\Media\Converters\Audio;

use Elegantly\Media\Converters\MediaConverter;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Facades\FFMpeg;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\TemporaryDirectory;
use Illuminate\Contracts\Filesystem\Filesystem;

class MediaOpusConverter extends MediaConverter
{
    /**
     * @param  string  $filename  Use an .opus, .ogg or .webm extension.
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
    public function __construct(
        Media $media,
        public string $filename,
        public string $bitrate = '96k',
        public ?int $channels = 2,
    ) {
        parent::__construct($media);
    }

    public function canExecute(Media $media, ?MediaConversion $parent): bool
    {
        $source = $parent ?? $media;

        return in_array($source->type, [MediaType::Audio, MediaType::Video]);
    }

    public function convert(
        Media $media,
        ?MediaConversion $parent,
        ?string $file,
        Filesystem $filesystem,
        TemporaryDirectory $temporaryDirectory
    ): ?MediaConversion {

        if (! $file) {
            return null;
        }

        $input = $filesystem->path($file);
        $output = $filesystem->path($this->filename);

        $ffmpeg = FFMpeg::audio();

        if (! $ffmpeg->decodableStreams($input)->hasAudio()) {
            return $this->unsupportedConversion();
        }

        $ffmpeg->opus(
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
