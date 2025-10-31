<?php

declare(strict_types=1);

namespace Elegantly\Media\Converters\Video;

use Elegantly\Media\Converters\Concerns\HasDimensions;
use Elegantly\Media\Converters\MediaConverter;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\FFMpeg\FFMpeg;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Illuminate\Contracts\Filesystem\Filesystem;
use Spatie\TemporaryDirectory\TemporaryDirectory as SpatieTemporaryDirectory;

class MediaFrameConverter extends MediaConverter
{
    use HasDimensions;

    public function __construct(
        public readonly Media $media,
        public string $filename,
        public int|float|string $timecode = 0,
        public ?int $width = null,
        public ?int $height = null,
    ) {}

    protected function getTimecode(Media $media, ?MediaConversion $parent): int|float|string
    {

        if (is_string($this->timecode)) {
            return $this->timecode;
        }

        $source = $parent ?? $media;

        $seconds = $source->duration / 1_000;

        if ($this->timecode > $seconds) {
            return floor($seconds);
        }

        return $this->timecode;
    }

    public function shouldExecute(Media $media, ?MediaConversion $parent): bool
    {
        $source = $parent ?? $media;

        return $source->type === MediaType::Video;
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

        if (! $ffmpeg->video()->hasVideo($input)) {
            return $this->skipConversion();
        }

        $source = $parent ?? $media;

        [$width, $height] = $this->getDimensions($source->width, $source->height, 2);

        $ffmpeg->video()->frame(
            input: $filesystem->path($file),
            output: $output,
            timecode: $this->getTimecode($media, $parent),
            width: $width,
            height: $height
        );

        return $media->addConversion(
            file: $output,
            conversionName: $this->conversion,
            parent: $parent,
        );

    }
}
