<?php

namespace Elegantly\Media\Definitions;

use Closure;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Illuminate\Contracts\Filesystem\Filesystem;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Spatie\ImageOptimizer\OptimizerChain;
use Spatie\TemporaryDirectory\TemporaryDirectory as SpatieTemporaryDirectory;

class MediaConversionImage extends MediaConversionDefinition
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
        public Fit $fit = Fit::Contain,
        public ?OptimizerChain $optimizerChain = null,
    ) {

        parent::__construct(
            name: $name,
            handle: fn () => null,
            when: $when,
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

        return ($parent ?? $media)->type === MediaType::Image;
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

        $fileName = $this->fileName ?? "{$media->name}.jpg";

        Image::load($filesystem->path($file))
            ->fit($this->fit, $this->width, $this->height)
            ->optimize($this->optimizerChain)
            ->save($filesystem->path($fileName));

        return $media->addConversion(
            file: $filesystem->path($fileName),
            conversionName: $this->name,
            parent: $parent,
        );

    }
}
