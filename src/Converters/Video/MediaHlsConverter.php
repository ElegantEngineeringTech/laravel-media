<?php

declare(strict_types=1);

namespace Elegantly\Media\Converters\Video;

use Elegantly\Media\Converters\MediaConverter;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Facades\FFMpeg;
use Elegantly\Media\Helpers\File;
use Elegantly\Media\Helpers\Hls;
use Elegantly\Media\Helpers\VideoRenditionCollection;
use Elegantly\Media\MediaServiceProvider;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\TemporaryDirectory;
use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * Encodes a video into an adaptive HLS stream, with one variant per rendition the source supports.
 *
 * The master playlist is the conversion file, and the media playlists and segments it references
 * are additional files stored next to it. The variants are stored in the conversion metadata, see Hls::toArray().
 *
 * @see Hls
 */
class MediaHlsConverter extends MediaConverter
{
    /**
     * @param  null|array<array-key, int>|VideoRenditionCollection  $renditions
     */
    public function __construct(
        Media $media,
        public ?int $fps = null,
        public int|float|null $duration = null,
        public null|array|VideoRenditionCollection $renditions = null,
        public int $startingRendition = 720,
        public int $segmentLength = 6,
        public int $crf = 23,
        public string $preset = 'slow',
        public string $playlist = 'master.m3u8',
    ) {
        parent::__construct($media);

        $this->playlist = File::sanitizeBasename($playlist);
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
            return $this->unsupportedConversion();
        }

        $ffmpeg = FFMpeg::video();

        $hls = $ffmpeg->hls(
            input: $filesystem->path($file),
            output: $temporaryDirectory->path(),
            playlist: $this->playlist,
            fps: $this->fps,
            duration: $this->duration,
            segmentLength: $this->segmentLength,
            crf: $this->crf,
            preset: $this->preset,
            renditions: $this->getRenditions(),
            startingRendition: $this->startingRendition,
        );

        if ($hls === false) {
            return $this->unsupportedConversion();
        }

        return $media->addConversion(
            file: $filesystem->path($this->playlist),
            conversionName: $this->conversion,
            parent: $parent,
            metadata: $hls->toArray(),
            additionalFiles: array_map(
                fn ($file) => $filesystem->path($file),
                $hls->files(),
            ),
        );

    }
}
