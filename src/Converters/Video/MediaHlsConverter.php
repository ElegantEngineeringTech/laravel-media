<?php

declare(strict_types=1);

namespace Elegantly\Media\Converters\Video;

use Elegantly\Media\Converters\MediaConverter;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\FFMpeg\FFMpeg;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Spatie\TemporaryDirectory\TemporaryDirectory as SpatieTemporaryDirectory;

class MediaHlsConverter extends MediaConverter
{
    /**
     * @param  null|array<int, string>  $variants
     */
    public function __construct(
        Media $media,
        public string $playlist = 'master.m3u8',
        public ?array $variants = null,
        public string $preset = 'veryslow',
    ) {
        parent::__construct($media);
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

        $outputId = Str::random(6);

        $input = $filesystem->path($file);
        $output = $filesystem->path($outputId);

        $ffmpeg = new FFMpeg;

        $ffmpeg->video()->hls(
            input: $input,
            output: $output,
            playlist: $this->playlist,
            preset: $this->preset,
            variants: $this->variants,
        );

        $master = $outputId.DIRECTORY_SEPARATOR.$this->playlist;

        $files = array_filter($filesystem->files($outputId), fn ($file) => $file !== $master);

        return $media->addConversion(
            file: $filesystem->path($master),
            additionalFiles: array_map(fn ($f) => $filesystem->path($f), $files),
            conversionName: $this->conversion,
            parent: $parent,
        );

    }
}
