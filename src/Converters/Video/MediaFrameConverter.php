<?php

declare(strict_types=1);

namespace Elegantly\Media\Converters\Video;

use Elegantly\Media\Converters\MediaConverter;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Facades\FFMpeg;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\TemporaryDirectory;
use Illuminate\Contracts\Filesystem\Filesystem;

class MediaFrameConverter extends MediaConverter
{
    public function __construct(
        Media $media,
        public string $filename,
        public int|float|string $timecode = 0,
        public ?int $width = null,
        public ?int $height = null,
    ) {
        parent::__construct($media);
    }

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

    public function canExecute(Media $media, ?MediaConversion $parent): bool
    {
        $source = $parent ?? $media;

        return $source->type === MediaType::Video;
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

        $ffmpeg = FFMpeg::video();

        if (! $ffmpeg->hasVideo($input)) {
            return $this->unsupportedConversion();
        }

        $ffmpeg->frame(
            input: $filesystem->path($file),
            output: $output,
            timecode: $this->getTimecode($media, $parent),
            width: $this->width,
            height: $this->height
        );

        return $media->addConversion(
            file: $output,
            conversionName: $this->conversion,
            parent: $parent,
        );

    }
}
