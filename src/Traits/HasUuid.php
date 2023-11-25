<?php

namespace Finller\LaravelMedia\Traits;

use Illuminate\Support\Str;

/**
 * @property ?string $uuid
 */
trait HasUuid
{
    // public static function bootHasUuid()
    // {
    //     static::creating(function (Model $model) {
    //         if (empty($model->uuid)) {
    //             $model->uuid = (string) Str::uuid(); // @phpstan-ignore-line
    //         }
    //     });
    // }

    function initializeHasUuid()
    {
        if (blank($this->uuid)) {
            $this->uuid = (string) Str::uuid();
        }
    }
}
