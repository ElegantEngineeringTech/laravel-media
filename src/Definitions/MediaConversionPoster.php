<?php

declare(strict_types=1);

namespace Elegantly\Media\Definitions;

use Closure;
use Elegantly\Media\Definitions\Concerns\HasFilename;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use FFMpeg\Coordinate\TimeCode;
use Illuminate\Contracts\Filesystem\Filesystem;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Spatie\ImageOptimizer\OptimizerChain;
use Spatie\TemporaryDirectory\TemporaryDirectory as SpatieTemporaryDirectory;

class MediaConversionPoster extends MediaConversionDefinition
{
    use HasFilename;

    /**
     * @param  null|string|(Closure(Media $media, ?MediaConversion $parent):string)  $fileName
     * @param  TimeCode|float|(Closure(Media $media, ?MediaConversion $parent):TimeCode)  $seconds
     */
    public function __construct(
        public string $name,
        public null|bool|Closure $when = null,
        public ?Closure $onCompleted = null,
        public bool $immediate = true,
        public bool $queued = false,
        public ?string $queue = null,
        public array $conversions = [],
        public null|Closure|string $fileName = null,
        public Closure|TimeCode|float $seconds = 0.0,
        public ?int $width = null,
        public ?int $height = null,
        public Fit $fit = Fit::Contain,
        public ?OptimizerChain $optimizerChain = null,
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

    public function getDefaultFilename(Media $media, ?MediaConversion $parent): string
    {

        $source = $parent ?? $media;

        return "{$source->name}.jpg";
    }

    public function getTimeCode(Media $media, ?MediaConversion $parent): TimeCode
    {
        $seconds = $this->seconds;

        if (is_float($seconds)) {
            return TimeCode::fromSeconds($seconds);
        }

        if ($seconds instanceof TimeCode) {
            return $seconds;
        }

        return $seconds($media, $parent);
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

        $fileName = $this->getFilename($media, $parent);

        FFMpeg::fromFilesystem($filesystem)
            ->open($file)
            ->getFrameFromTimecode($this->getTimeCode($media, $parent))
            ->export()
            ->save($fileName);

        Image::load($filesystem->path($fileName))
            ->fit($this->fit, $this->width, $this->height)
            ->optimize($this->optimizerChain)
            ->save();

        return $media->addConversion(
            file: $filesystem->path($fileName),
            conversionName: $this->name,
            parent: $parent,
        );

    }
}
