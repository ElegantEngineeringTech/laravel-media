<?php

declare(strict_types=1);

namespace Elegantly\Media\Converters\Video;

use Elegantly\Media\Converters\MediaConverter;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Facades\FFMpeg;
use Elegantly\Media\Helpers\VideoRenditionCollection;
use Elegantly\Media\MediaServiceProvider;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\TemporaryDirectory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MediaHlsConverter extends MediaConverter
{
    /**
     * @param  null|array<array-key, int>|VideoRenditionCollection  $renditions
     */
    public function __construct(
        Media $media,
        public ?int $fps = null,
        public null|array|VideoRenditionCollection $renditions = null,
        public int $startingRendition = 720,
        public int $segmentLength = 6,
        public int $crf = 23,
        public string $preset = 'slow',
        public string $playlist = 'master.m3u8',
    ) {
        parent::__construct($media);
    }

    public function canExecute(Media $media, ?MediaConversion $parent): bool
    {
        $source = $parent ?? $media;

        return $source->type === MediaType::Video;
    }

    public function getRenditions(): ?VideoRenditionCollection
    {
        if (is_array($this->renditions)) {
            return MediaServiceProvider::getVideoHlsRenditions($this->renditions);
        }

        return $this->renditions;
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

        $outputId = Str::random(6);

        $input = $filesystem->path($file);
        $output = $filesystem->path($outputId);

        $ffmpeg = FFMpeg::video();

        $result = $ffmpeg->hls(
            input: $input,
            output: $output,
            playlist: $this->playlist,
            fps: $this->fps,
            segmentLength: $this->segmentLength,
            crf: $this->crf,
            preset: $this->preset,
            renditions: $this->getRenditions(),
            startingRendition: $this->startingRendition,
        );

        if ($result === false) {
            return $this->unsupportedConversion();
        }

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
