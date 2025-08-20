<?php

declare(strict_types=1);

namespace Elegantly\Media\Converters;

use Elegantly\Media\Enums\MediaConversionState;
use Elegantly\Media\Events\MediaConverterExecutedEvent;
use Elegantly\Media\MediaConversionDefinition;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\TemporaryDirectory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Spatie\TemporaryDirectory\TemporaryDirectory as SpatieTemporaryDirectory;
use Throwable;

abstract class MediaConverter implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

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

    protected function skipConversion(): MediaConversion
    {
        return $this->media->replaceConversion(new MediaConversion([
            'conversion_name' => $this->conversion,
            'media_id' => $this->media->id,
            'state' => MediaConversionState::Skipped,
            'state_set_at' => now(),
        ]));
    }

    protected function failConversion(?Throwable $exception = null): ?MediaConversion
    {

        if (! $this->media->hasConversion($this->conversion)) {

            return $this->media->replaceConversion(new MediaConversion([
                'conversion_name' => $this->conversion,
                'media_id' => $this->media->id,
                'state' => MediaConversionState::Failed,
                'state_set_at' => now(),
                'contents' => $exception ? ($exception->getCode().': '.$exception->getMessage()) : null,
            ]));

        }

        return null;
    }

    abstract public function convert(
        Media $media,
        ?MediaConversion $parent,
        ?string $file,
        Filesystem $filesystem,
        SpatieTemporaryDirectory $temporaryDirectory
    ): ?MediaConversion;

    abstract public function shouldExecute(
        Media $media,
        ?MediaConversion $parent,
    ): bool;

    public function handle(): ?MediaConversion
    {

        if (str_contains($this->conversion, '.')) {

            $parentConversion = str($this->conversion)->beforeLast('.')->value();

            $parent = $this->media->getOrExecuteConversion(
                $parentConversion,
                withChildren: false
            );

            /**
             * Parent conversion failed to execute
             */
            if ($parent === null || $parent->state !== MediaConversionState::Succeeded) {
                return null;
            }

        } else {
            $parent = null;
        }

        $definition = $this->getDefinition();

        /**
         * Conversion definition was removed
         */
        if (! $definition) {
            $this->media->deleteConversion($this->conversion);

            return null;
        }

        if (! $definition->shouldExecute($this->media, $parent)) {
            return $this->skipConversion();
        }

        if (! $this->shouldExecute($this->media, $parent)) {
            return $this->skipConversion();
        }

        $mediaConversion = TemporaryDirectory::callback(function ($temporaryDirectory) use ($parent) {

            $storage = TemporaryDirectory::storage($temporaryDirectory);

            $source = $this->parent ?? $this->media;

            $copy = $source->path ? $source->copyFileTo(
                disk: $storage,
                path: $source->path
            ) : null;

            return $this->convert($this->media, $parent, $copy, $storage, $temporaryDirectory);
        });

        if ($onCompleted = $definition->onCompleted) {
            $onCompleted($mediaConversion, $this->media, $parent);
        }

        if (
            $mediaConversion &&
            $mediaConversion->state === MediaConversionState::Succeeded &&
            $this->withChildren
        ) {
            $this->media->generateConversions(
                parent: $mediaConversion,
                filter: fn ($definition) => $definition->immediate,
                force: false,
            );
        }

        event(new MediaConverterExecutedEvent($this));

        return $mediaConversion;
    }

    public function failed(?Throwable $exception): void
    {
        $this->failConversion($exception);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'media',
            "conversion:{$this->conversion}",
            "{$this->media->model_type}:{$this->media->model_id}",
            get_class($this->media).":{$this->media->id}",
        ];
    }
}
