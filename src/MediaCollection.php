<?php

namespace Elegantly\Media;

use Closure;
use Illuminate\Support\Collection;

/**
 * @property Collection<int, MediaConversion> $conversions
 * @property null|string|(Closure(): string) $fallback
 */
class MediaCollection
{
    public function __construct(
        public string $name,
        public ?array $acceptedMimeTypes = null,
        public bool $single = false,
        public bool $public = false,
        public ?string $disk = null,
        public null|string|Closure $fallback = null,
    ) {
        //
    }
}
