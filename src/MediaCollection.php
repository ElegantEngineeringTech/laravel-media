<?php

namespace Finller\LaravelMedia;

use Illuminate\Support\Collection;

/**
 * @property Collection<int, MediaConversion> $conversions
 */
class MediaCollection
{
    public function __construct(
        public string $name,
        public ?array $acceptedMimeTypes = null,
        public bool $single = false,
        public bool $public = false,
    ) {
    }
}
