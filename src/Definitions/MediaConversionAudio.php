<?php

declare(strict_types=1);

namespace Elegantly\Media\Definitions;

use Closure;
use Elegantly\Media\Definitions\Concerns\HasFilename;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use FFMpeg\Format\Audio\Mp3;
use FFMpeg\Format\FormatInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use ProtoneMedia\LaravelFFMpeg\FFMpeg\FFProbe;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Spatie\TemporaryDirectory\TemporaryDirectory as SpatieTemporaryDirectory;

class MediaConversionAudio extends MediaConversionDefinition
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
        public null|Closure|string $fileName = null,
        public FormatInterface $format = new Mp3,
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

    public function hasFileAudioStream(string $path): bool
    {
        $ffprobe = FFProbe::create([
            'ffmpeg.binaries' => config('laravel-ffmpeg.ffmpeg.binaries'),
            'ffprobe.binaries' => config('laravel-ffmpeg.ffprobe.binaries'),
        ]);

        $streams = $ffprobe->streams($path);

        return (bool) $streams->audios()->count();
    }

    public function getDefaultFilename(Media $media, ?MediaConversion $parent): string
    {
        $source = $parent ?? $media;

        return "{$source->name}.mp3";
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

        /**
         * Videos do not always have an audio stream
         */
        if (! $this->hasFileAudioStream($filesystem->path($file))) {
            return null;
        }

        $fileName = $this->getFilename($media, $parent);

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
