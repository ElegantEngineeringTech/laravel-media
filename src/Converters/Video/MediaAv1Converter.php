<?php

declare(strict_types=1);

namespace Elegantly\Media\Converters\Video;

use Elegantly\Media\Converters\MediaConverter;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Facades\FFMpeg;
use Elegantly\Media\Helpers\File;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\TemporaryDirectory;
use Illuminate\Contracts\Filesystem\Filesystem;

class MediaAv1Converter extends MediaConverter
{
    /**
     * @param  string  $filename  Use an .mp4 or .webm extension.
     */
    public function __construct(
        Media $media,
        public string $filename,
        public ?int $width = null,
        public ?int $height = null,
        public ?int $fps = null,
        public int|float|null $duration = null,
        public int $crf = 35,
        public int $preset = 6,
    ) {
        parent::__construct($media);

        $this->filename = File::sanitizeBasename($filename);
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
            return $this->unsupportedConversion();
        }

        $input = $filesystem->path($file);
        $output = $filesystem->path($this->filename);

        $ffmpeg = FFMpeg::video();

        $ffmpeg->av1(
            input: $input,
            output: $output,
            width: $this->width,
            height: $this->height,
            upscale: false,
            fps: $this->fps,
            duration: $this->duration,
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
