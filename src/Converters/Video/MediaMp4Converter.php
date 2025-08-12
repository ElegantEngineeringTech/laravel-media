<?php

declare(strict_types=1);

namespace Elegantly\Media\Converters\Video;

use Elegantly\Media\Converters\Concerns\HasDimensions;
use Elegantly\Media\Converters\MediaConverter;
use Elegantly\Media\FFMpeg\FFMpeg;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Illuminate\Contracts\Filesystem\Filesystem;
use Spatie\TemporaryDirectory\TemporaryDirectory as SpatieTemporaryDirectory;

class MediaMp4Converter extends MediaConverter
{
    use HasDimensions;

    public function __construct(
        public readonly Media $media,
        public string $filename,
        public ?int $width = null,
        public ?int $height = null,
        public int $crf = 18,
        public string $preset = 'veryslow',
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

        if (! $ffmpeg->video()->hasVideo($input)) {
            return null;
        }

        $source = $parent ?? $media;

        [$width, $height] = $this->getDimensions($source->width, $source->height, 2);

        $ffmpeg->video()->mp4(
            input: $input,
            output: $output,
            width: $width,
            height: $height,
            crf: $this->crf,
            preset: $this->preset,
        );

        return $media->addConversion(
            file: $output,
            conversionName: $this->conversion,
            parent: $parent,
        );

    }
}
