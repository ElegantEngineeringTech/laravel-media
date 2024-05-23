<?php

namespace ElegantEngineeringTech\Media\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class GeneratedConversions implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_null($value)) {
            return collect();
        }

        return collect(json_decode($value, true))->map(fn ($item) => GeneratedConversion::make($item));
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  Collection|array|null  $value
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_null($value)) {
            return json_encode([]);
        }

        if ($value instanceof Collection) {
            return json_encode($value->toArray());
        }

        return json_encode($value);
    }
}
