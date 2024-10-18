<?php

namespace Elegantly\Media\Definitions;

use Closure;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use FFMpeg\Format\Audio\Mp3;
use FFMpeg\Format\FormatInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Spatie\TemporaryDirectory\TemporaryDirectory as SpatieTemporaryDirectory;

class MediaConversionAudio extends MediaConversionDefinition
{
    /**
     * @param  MediaConversionDefinition[]  $conversions
     * @param  null|bool|Closure(Media $media, ?MediaConversion $parent): bool  $when
     */
    public function __construct(
        public string $name,
        public null|bool|Closure $when = null,
        public bool $immediate = true,
        public bool $queued = true,
        public ?string $queue = null,
        public array $conversions = [],
        public ?string $fileName = null,
        public FormatInterface $format = new Mp3,
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

        $fileName = $this->fileName ?? "{$media->name}.mp3";

        $ffmpeg = FFMpeg::fromFilesystem($filesystem)
            ->open($file)
            ->export()
            ->inFormat($this->format);

        $ffmpeg->save($fileName);

        return $media->addConversion(
            file: $filesystem->path($fileName),
            conversionName: $this->name,
            parent: $parent,
        );

    }
}
