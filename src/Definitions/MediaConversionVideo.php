<?php

declare(strict_types=1);

namespace Elegantly\Media\Definitions;

use Closure;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use FFMpeg\Coordinate\AspectRatio;
use FFMpeg\Filters\Video\ResizeFilter;
use FFMpeg\Format\FormatInterface;
use FFMpeg\Format\Video\X264;
use Illuminate\Contracts\Filesystem\Filesystem;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Spatie\TemporaryDirectory\TemporaryDirectory as SpatieTemporaryDirectory;

class MediaConversionVideo extends MediaConversionDefinition
{
    public function __construct(
        public string $name,
        public null|bool|Closure $when = null,
        public ?Closure $onCompleted = null,
        public bool $immediate = true,
        public bool $queued = true,
        public ?string $queue = null,
        public array $conversions = [],
        public ?string $fileName = null,
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

        $fileName = $this->fileName ?? "{$media->name}.mp4";

        $source = $parent ?? $media;

        $ratio = new AspectRatio($source->aspect_ratio);

        $modulus = match (true) {
            $this->format instanceof X264 => 2, // dimensions must be divisible by 2
            default => 1,
        };

        $width = $this->width ?? ($this->height ? $ratio->calculateWidth($this->height, $modulus) : null);
        $height = $this->height ?? ($this->width ? $ratio->calculateHeight($this->width, $modulus) : null);

        $ffmpeg = FFMpeg::fromFilesystem($filesystem)
            ->open($file)
            ->export()
            ->inFormat($this->format);

        if ($width && $height) {
            $ffmpeg->resize(
                $width,
                $height,
                $this->fitMethod,
                $this->forceStandards
            );
        }

        $ffmpeg->save($fileName);

        return $media->addConversion(
            file: $filesystem->path($fileName),
            conversionName: $this->name,
            parent: $parent,
        );

    }
}
