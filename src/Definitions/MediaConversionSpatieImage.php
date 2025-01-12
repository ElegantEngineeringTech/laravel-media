<?php

declare(strict_types=1);

namespace Elegantly\Media\Definitions;

use Closure;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Illuminate\Contracts\Filesystem\Filesystem;
use Spatie\Image\Enums\ImageDriver;
use Spatie\Image\Image;
use Spatie\TemporaryDirectory\TemporaryDirectory as SpatieTemporaryDirectory;

class MediaConversionSpatieImage extends MediaConversionDefinition
{
    /**
     * @param  Closure(Image $image, Media $media, ?MediaConversion $parent):void  $manipulate
     * @param  Closure(Media $media, ?MediaConversion $parent):string  $fileName
     */
    public function __construct(
        public string $name,
        public Closure $manipulate,
        public Closure $fileName,
        public ImageDriver $driver,
        public null|bool|Closure $when = null,
        public ?Closure $onCompleted = null,
        public bool $immediate = true,
        public bool $queued = true,
        public ?string $queue = null,
        public array $conversions = [],
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

        return $source->type === MediaType::Image;
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

        $manipulate = $this->manipulate;

        $fileName = $this->fileName;
        $newFile = $fileName($media, $parent);

        $image = Image::load($filesystem->path($file));

        $manipulate($image, $media, $parent);

        $image->save($filesystem->path($newFile));

        return $media->addConversion(
            file: $filesystem->path($newFile),
            conversionName: $this->name,
            parent: $parent,
        );

    }
}
