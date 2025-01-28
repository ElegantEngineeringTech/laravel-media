<?php

declare(strict_types=1);

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

class MediaConversionPdfPreview extends MediaConversionDefinition
{
    /**
     * @param  null|string|(Closure(Media $media, ?MediaConversion $parent):string)  $fileName
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
        public int $page = 1,
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
        $source = $parent ?? $media;

        return $source->type === MediaType::Pdf;
    }

    public function getFileName(Media $media, ?MediaConversion $parent): string
    {
        if ($fileName = $this->fileName) {
            return is_string($fileName) ? $fileName : $fileName($media, $parent);
        }

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

        $path = $filesystem->path($file);

        $fileName = $this->getFileName($media, $parent);

        $target = $filesystem->path($fileName);

        $pdf = new \Spatie\PdfToImage\Pdf($path);
        $pdf
            ->selectPage($this->page)
            ->save($target);

        Image::load($target)
            ->fit($this->fit, $this->width, $this->height)
            ->optimize($this->optimizerChain)
            ->save();

        return $media->addConversion(
            file: $target,
            conversionName: $this->name,
            parent: $parent,
        );

    }
}
