<?php

namespace Finller\LaravelMedia;

use Illuminate\Support\Collection;

/**
 * @property Collection<int, MediaConversion> $conversions
 */
class MediaCollection
{
    public function __construct(
        public ?array $acceptedMimeTypes = null,
        public Collection $conversions = new Collection()
    ) {
    }
}
