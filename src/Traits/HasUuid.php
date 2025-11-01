<?php

declare(strict_types=1);

namespace Elegantly\Media\Traits;

use Illuminate\Support\Str;

/**
 * @property string $uuid
 */
trait HasUuid
{
    public function initializeHasUuid(): void
    {
        if (blank($this->uuid)) {
            $this->uuid = (string) Str::uuid();
        }
    }
}
