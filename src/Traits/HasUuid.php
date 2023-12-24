<?php

namespace Finller\Media\Traits;

use Illuminate\Support\Str;

/**
 * @property ?string $uuid
 */
trait HasUuid
{
    public function initializeHasUuid()
    {
        if (blank($this->uuid)) {
            $this->uuid = (string) Str::uuid();
        }
    }
}
