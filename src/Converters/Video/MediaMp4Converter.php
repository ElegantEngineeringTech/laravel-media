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

class MediaMp4Converter extends MediaConverter
{
    public function __construct(
        Media $media,
        public string $filename,
        public ?int $width = null,
        public ?int $height = null,
        public ?int $fps = null,
        public int $crf = 23,
        public string $preset = 'slow',
    ) {
        parent::__construct($media);
    }

    public function canExecute(Media $media, ?MediaConversion $parent): bool
    {
        $source = $parent ?? $media;

        return $source->type === MediaType::Video || in_array($source->mime_type, ['image/gif']);
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

        $ffmpeg->mp4(
            input: $input,
            output: $output,
            width: $this->width,
            height: $this->height,
            upscale: false,
            fps: $this->fps,
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
