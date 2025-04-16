<?php

declare(strict_types=1);

namespace Elegantly\Media\Definitions;

use Closure;
use Elegantly\Media\Definitions\Concerns\HasFilename;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use FFMpeg\Coordinate\AspectRatio;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Filters\Video\ResizeFilter;
use FFMpeg\Filters\Video\VideoFilters;
use FFMpeg\Format\Video\X264;
use FFMpeg\Format\VideoInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Spatie\TemporaryDirectory\TemporaryDirectory as SpatieTemporaryDirectory;

class MediaConversionVideo extends MediaConversionDefinition
{
    use HasFilename;

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
        public VideoInterface $format = new X264,
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
        if ($this->when === null) {
            return ($parent ?? $media)->type === MediaType::Video;
        }

        return parent::shouldExecute($media, $parent);
    }

    public function getDefaultFilename(Media $media, ?MediaConversion $parent): string
    {
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

        $source = $parent ?? $media;

        $fileName = $this->getFilename($media, $parent);
        $aspectRatio = new AspectRatio($source->aspect_ratio);

        $width = min($this->width, $source->width);
        $height = min($this->height, $source->height);

        $ffmpeg = FFMpeg::fromFilesystem($filesystem)
            ->open($file)
            ->export()
            ->inFormat($this->format);

        if ($width && $height) {
            $ffmpeg->addFilter(fn (VideoFilters $filters) => $filters->resize(
                dimension: new Dimension($width, $height),
                mode: $this->fitMethod,
                forceStandards: $this->forceStandards,
            ));
        } elseif ($width) {

            $height = $aspectRatio->calculateHeight($width, $this->format->getModulus());

            $ffmpeg->addFilter(fn (VideoFilters $filters) => $filters->resize(
                dimension: new Dimension($width, $height),
                mode: ResizeFilter::RESIZEMODE_FIT,
                forceStandards: $this->forceStandards,
            ));
        } elseif ($height) {
            $width = $aspectRatio->calculateWidth($height, $this->format->getModulus());

            $ffmpeg->addFilter(fn (VideoFilters $filters) => $filters->resize(
                dimension: new Dimension($width, $height),
                mode: ResizeFilter::RESIZEMODE_FIT,
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
