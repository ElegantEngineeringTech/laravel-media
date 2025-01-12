<?php

declare(strict_types=1);

namespace Elegantly\Media\Definitions;

use Closure;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Illuminate\Contracts\Filesystem\Filesystem;
use ProtoneMedia\LaravelFFMpeg\Exporters\MediaExporter;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Spatie\TemporaryDirectory\TemporaryDirectory as SpatieTemporaryDirectory;

class MediaConversionFFmpeg extends MediaConversionDefinition
{
    /**
     * @param  Closure(MediaExporter $ffmpeg, Media $media, ?MediaConversion $parent):void  $manipulate
     * @param  Closure(Media $media, ?MediaConversion $parent):string  $fileName
     */
    public function __construct(
        public string $name,
        public Closure $manipulate,
        public Closure $fileName,
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

        return in_array($source->type, [MediaType::Video, MediaType::Audio]);
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

        $ffmpeg = FFMpeg::fromFilesystem($filesystem)
            ->open($file)
            ->export();

        $manipulate($ffmpeg, $media, $parent);

        $ffmpeg->save($newFile);

        return $media->addConversion(
            file: $filesystem->path($newFile),
            conversionName: $this->name,
            parent: $parent,
        );

    }
}
