<?php

declare(strict_types=1);

namespace Elegantly\Media\Definitions;

use Closure;
use Elegantly\Media\Definitions\Concerns\HasFilename;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Helpers\File;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Illuminate\Contracts\Filesystem\Filesystem;
use ProtoneMedia\LaravelFFMpeg\Filters\TileFactory;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Spatie\TemporaryDirectory\TemporaryDirectory as SpatieTemporaryDirectory;

class MediaConversionSpritesheet extends MediaConversionDefinition
{
    use HasFilename;

    /**
     * @param  float  $interval  in seconds
     * @param  MediaConversionDefinition[]  $conversions
     * @param  null|bool|Closure(Media $media, ?MediaConversion $parent): bool  $when
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
        public float $interval = 3.0,
        public int $width = 180,
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

        $source = $parent ?? $media;

        return $source->type === MediaType::Video;
    }

    public function getDefaultFilename(Media $media, ?MediaConversion $parent): string
    {
        $source = $parent ?? $media;

        return "{$source->name}.jpg";
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

        $newFile = $this->getFilename($media, $parent);

        $ffmpeg = FFMpeg::fromFilesystem($filesystem)
            ->open($file);

        // in ms
        $duration = File::duration($filesystem->path($file)) ?? 0.0;

        $count = (int) ceil(($duration / 1_000) / $this->interval);

        $ffmpeg = $ffmpeg->exportTile(
            fn (TileFactory $tileFactory) => $tileFactory
                ->interval($this->interval)
                ->grid(1, $count)
                ->scale($this->width)
        );

        $ffmpeg->save($newFile);

        return $media->addConversion(
            file: $filesystem->path($newFile),
            conversionName: $this->name,
            parent: $parent,
        );

    }
}
