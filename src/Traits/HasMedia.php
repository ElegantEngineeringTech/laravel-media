<?php

namespace Finller\LaravelMedia\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property ?string $uuid
 */
trait HasMedia
{
    public function media(): MorphMany
    {
        return $this->morphMany(config('media-library.media_model'), 'model');
    }
}
