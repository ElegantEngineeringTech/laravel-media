<?php

declare(strict_types=1);

namespace Elegantly\Media\Converters\Audio;

use Elegantly\Media\Converters\MediaConverter;
use Elegantly\Media\FFMpeg\FFMpeg;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Illuminate\Contracts\Filesystem\Filesystem;
use Spatie\TemporaryDirectory\TemporaryDirectory as SpatieTemporaryDirectory;

class MediaWavConverter extends MediaConverter
{
    public function __construct(
        public readonly Media $media,
        public string $filename,
    ) {}

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
            return null;
        }

        $ffmpeg->audio()->wav(
            input: $input,
            output: $output,
        );

        return $media->addConversion(
            file: $output,
            conversionName: $this->conversion,
            parent: $parent,
        );

    }
}
