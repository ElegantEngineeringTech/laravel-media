<?php

namespace Finller\LaravelMedia\Database\Factories;

use Finller\LaravelMedia\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @template Media
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition()
    {
        return [
            'model_id' => 0,
            'model_type' => '\App\Models\Fake',
            'generated_conversions' => [],
        ];
    }
}
