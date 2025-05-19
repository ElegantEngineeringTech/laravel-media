<?php

declare(strict_types=1);

namespace Elegantly\Media\Definitions;

use Closure;
use Elegantly\Media\Jobs\MediaConversionJob;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\TemporaryDirectory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Bus\PendingDispatch;
use Spatie\TemporaryDirectory\TemporaryDirectory as SpatieTemporaryDirectory;

class MediaConversionDefinition
{
    /**
     * @param  bool  $immediate  Determine if the conversion should be dispatched immediatly and synchronously after the media is added
     * @param  MediaConversionDefinition[]  $conversions
     * @param  Closure(Media $media, ?MediaConversion $parent, ?string $file, Filesystem $filesystem, SpatieTemporaryDirectory $temporaryDirectory): ?MediaConversion  $handle
     * @param  null|bool|Closure(Media $media, ?MediaConversion $parent): bool  $when
     * @param  null|(Closure(?MediaConversion $conversion, Media $media, ?MediaConversion $parent): void)  $onCompleted
     */
    public function __construct(
        public string $name,
        public Closure $handle,
        public null|bool|Closure $when = null,
        public ?Closure $onCompleted = null,
        public bool $immediate = true,
        public bool $queued = true,
        public ?string $queue = null,
        public \DateTimeInterface|\DateInterval|int|null $delay = null,
        public array $conversions = [],
    ) {
        /** @var array<string, MediaConversionDefinition> $conversions */
        $conversions = collect($conversions)->keyBy('name')->all();
        $this->conversions = $conversions;
    }

    public function handle(
        Media $media,
        ?MediaConversion $parent,
        ?string $file,
        Filesystem $filesystem,
        SpatieTemporaryDirectory $temporaryDirectory
    ): ?MediaConversion {
        $handle = $this->handle;

        return $handle($media, $parent, $file, $filesystem, $temporaryDirectory);
    }

    public function shouldExecute(Media $media, ?MediaConversion $parent): bool
    {
        $when = $this->when;

        if ($when === null) {
            return true;
        }

        if (is_bool($when)) {
            return $when;
        }

        return (bool) $when($media, $parent);
    }

    public function dispatch(Media $media, ?MediaConversion $parent): PendingDispatch
    {
        $job = new MediaConversionJob(
            media: $media,
            conversion: $parent ? "{$parent->conversion_name}.{$this->name}" : $this->name
        );

        return dispatch($job)
            ->onQueue($this->queue ?? $job->queue)
            ->delay($this->delay ?? $job->delay); // @phpstan-ignore-line
    }

    public function execute(Media $media, ?MediaConversion $parent): ?MediaConversion
    {
        $value = TemporaryDirectory::callback(function ($temporaryDirectory) use ($media, $parent) {

            $storage = TemporaryDirectory::storage($temporaryDirectory);

            $source = $parent ?? $media;

            $copy = $source->path ? $source->copyFileTo(
                disk: $storage,
                path: $source->path
            ) : null;

            return $this->handle($media, $parent, $copy, $storage, $temporaryDirectory);

        });

        if ($onCompleted = $this->onCompleted) {
            $onCompleted($value, $media, $parent);
        }

        return $value;
    }
}
