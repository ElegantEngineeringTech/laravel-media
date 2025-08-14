<?php

declare(strict_types=1);

namespace Elegantly\Media;

use Closure;
use Elegantly\Media\Models\Media;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class MediaCollection
{
    /**
     * @param  null|(string[])  $acceptedMimeTypes
     * @param  null|string|(Closure(): null|string)  $fallback
     * @param  null|(Closure(UploadedFile|File $file, TemporaryDirectory $temporaryDirectory): (UploadedFile|File))  $transform
     * @param  null|(Closure(Media $media): void)  $onAdded
     * @param  MediaConversionDefinition[]  $conversions
     */
    public function __construct(
        public string $name,
        public ?array $acceptedMimeTypes = null,
        public bool $single = false,
        public bool $public = false,
        public ?string $disk = null,
        public null|string|Closure $fallback = null,
        public ?Closure $transform = null,
        public ?Closure $onAdded = null,
        public array $conversions = [],
    ) {
        /** @var array<string, MediaConversionDefinition> $conversions */
        $conversions = collect($conversions)->keyBy('name')->all();
        $this->conversions = $conversions;
    }

    public function getConversionDefinition(string $name): ?MediaConversionDefinition
    {
        /** @var ?MediaConversionDefinition */
        $value = data_get(
            target: $this->conversions,
            key: str_replace('.', '.conversions.', $name)
        );

        return $value;
    }
}
