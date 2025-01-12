<?php

declare(strict_types=1);

namespace Elegantly\Media\Definitions;

use Closure;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Filters\Video\ResizeFilter;
use FFMpeg\Filters\Video\VideoFilters;
use FFMpeg\Format\FormatInterface;
use FFMpeg\Format\Video\X264;
use Illuminate\Contracts\Filesystem\Filesystem;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Spatie\TemporaryDirectory\TemporaryDirectory as SpatieTemporaryDirectory;

class MediaConversionVideo extends MediaConversionDefinition
{
    /**
     * @param  null|string|(Closure(Media $media, ?MediaConversion $parent):string)  $fileName
     */
    public function __construct(
        public string $name,
        public null|bool|Closure $when = null,
        public ?Closure $onCompleted = null,
        public bool $immediate = true,
        public bool $queued = true,
        public ?string $queue = null,
        public array $conversions = [],
        public null|string|Closure $fileName = null,
        public ?int $width = null,
        public ?int $height = null,
        public FormatInterface $format = new X264,
        public string $fitMethod = ResizeFilter::RESIZEMODE_INSET,
        public bool $forceStandards = false,
    ) {

        parent::__construct(
            name: $name,
            handle: fn () => null,
            when: $when,
            onCompleted: $onCompleted,
            immediate: $immediate,
            queued: $queued,
            queue: $queue,
            conversions: $conversions
        );
    }

    public function shouldExecute(Media $media, ?MediaConversion $parent): bool
    {
        if ($this->when !== null) {
            return parent::shouldExecute($media, $parent);
        }

        return ($parent ?? $media)->type === MediaType::Video;
    }

    public function getFileName(Media $media, ?MediaConversion $parent): string
    {
        if ($fileName = $this->fileName) {
            return is_string($fileName) ? $fileName : $fileName($media, $parent);
        }

        $source = $parent ?? $media;

        return "{$source->name}.mp4";
    }

    public function handle(
        Media $media,
        ?MediaConversion $parent,
        ?string $file,
        Filesystem $filesystem,
        SpatieTemporaryDirectory $temporaryDirectory
    ): ?MediaConversion {

        if (! $file) {
            return null;
        }

        $fileName = $this->getFileName($media, $parent);

        $ffmpeg = FFMpeg::fromFilesystem($filesystem)
            ->open($file)
            ->export()
            ->inFormat($this->format);

        if ($this->width && $this->height) {
            $ffmpeg->addFilter(fn (VideoFilters $filters) => $filters->resize(
                dimension: new Dimension($this->width, $this->height),
                mode: $this->fitMethod,
                forceStandards: $this->forceStandards,
            ));
        } elseif ($this->width) {
            $ffmpeg->addFilter(fn (VideoFilters $filters) => $filters->resize(
                dimension: new Dimension($this->width, 1),
                mode: ResizeFilter::RESIZEMODE_SCALE_HEIGHT,
                forceStandards: $this->forceStandards,
            ));
        } elseif ($this->height) {
            $ffmpeg->addFilter(fn (VideoFilters $filters) => $filters->resize(
                dimension: new Dimension(1, $this->height),
                mode: ResizeFilter::RESIZEMODE_SCALE_WIDTH,
                forceStandards: $this->forceStandards,
            ));
        }

        $ffmpeg->save($fileName);

        return $media->addConversion(
            file: $filesystem->path($fileName),
            conversionName: $this->name,
            parent: $parent,
        );

    }
}
