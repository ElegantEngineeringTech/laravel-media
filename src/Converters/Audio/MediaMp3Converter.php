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

class MediaMp3Converter extends MediaConverter
{
    /**
     * @param  null|int  $channels
     *                              - null : Keep the source channel layout.
     *                              - 1    : Mono — single channel audio, smallest file size, good for voice.
     *                              - 2    : Stereo — two-channel audio (MP3 supports at most 2 channels).
     */
    public function __construct(
        Media $media,
        public string $filename,
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

        $ffmpeg->mp3(
            input: $input,
            output: $output,
            channels: $this->channels,
        );

        return $media->addConversion(
            file: $output,
            conversionName: $this->conversion,
            parent: $parent,
        );

    }
}
