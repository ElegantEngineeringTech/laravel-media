<?php

namespace Elegantly\Media;

use Closure;
use Elegantly\Media\Definitions\MediaConversionDefinition;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;

class MediaCollection
{
    /**
     * @param  null|(string[])  $acceptedMimeTypes
     * @param  null|string|(Closure(): null|string)  $fallback
     * @param  null|(Closure(UploadedFile|File $file): (UploadedFile|File))  $transform
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
        public array $conversions = [],
    ) {
        /** @var array<string, MediaConversionDefinition> $conversions */
        $conversions = collect($conversions)->keyBy('name')->toArray();
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
