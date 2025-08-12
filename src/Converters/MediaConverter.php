<?php

declare(strict_types=1);

namespace Elegantly\Media\Converters;

use Elegantly\Media\MediaConversionDefinition;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\TemporaryDirectory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Spatie\TemporaryDirectory\TemporaryDirectory as SpatieTemporaryDirectory;

abstract class MediaConverter implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public string $conversion;

    public bool $withChildren = true;

    public function __construct(
        public readonly Media $media,
    ) {}

    public function conversion(string $value): static
    {
        $this->conversion = $value;

        return $this;
    }

    public function withChildren(bool $value = true): static
    {
        $this->withChildren = $value;

        return $this;
    }

    public function uniqueId(): string
    {
        return "{$this->media->id}:{$this->conversion}";
    }

    public function getDefinition(): ?MediaConversionDefinition
    {
        return $this->media->getConversionDefinition($this->conversion);
    }

    abstract public function convert(
        Media $media,
        ?MediaConversion $parent,
        ?string $file,
        Filesystem $filesystem,
        SpatieTemporaryDirectory $temporaryDirectory
    ): ?MediaConversion;

    public function handle(): ?MediaConversion
    {

        if (str_contains($this->conversion, '.')) {

            $parent = $this->media->getOrExecuteConversion(
                str($this->conversion)->beforeLast('.')->value()
            );

            /**
             * Bail because parent conversion failed to execute
             */
            if ($parent === null) {
                return null;
            }
        } else {
            $parent = null;
        }

        $definition = $this->getDefinition();

        if (! $definition) {
            return null;
        }

        if (! $definition->shouldExecute($this->media, $parent)) {
            return null;
        }

        $value = TemporaryDirectory::callback(function ($temporaryDirectory) use ($parent) {

            $storage = TemporaryDirectory::storage($temporaryDirectory);

            $source = $this->parent ?? $this->media;

            $copy = $source->path ? $source->copyFileTo(
                disk: $storage,
                path: $source->path
            ) : null;

            return $this->convert($this->media, $parent, $copy, $storage, $temporaryDirectory);
        });

        return $value;
    }
}
