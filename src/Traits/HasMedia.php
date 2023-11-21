<?php

namespace Finller\LaravelMedia\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * @property ?string $uuid
 */
trait HasMedia
{
    public function media(): MorphMany
    {
        return $this->morphMany(config('media-library.media_model'), 'model');
    }

    function getMediaCollections(): Collection
    {
        return collect([]);
    }
}
