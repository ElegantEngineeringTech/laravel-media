<?php

namespace Finller\LaravelMedia\Database\Factories;

use Finller\LaravelMedia\Casts\GeneratedConversion;
use Finller\LaravelMedia\Enums\MediaType;
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
            'disk' => config('media.disk'),
            'model_id' => 0,
            'model_type' => '\App\Models\Fake',
            'generated_conversions' => [],
        ];
    }

    public static function generatedConversion()
    {
        return new GeneratedConversion(
            state: 'success',
            type: MediaType::Image,
            file_name: 'poster.png',
            name: 'poster',
            path: '/poster/poster.png',
            disk: config('media.disk'),
            conversions: collect([
                '480p' => new GeneratedConversion(
                    state: 'success',
                    type: MediaType::Image,
                    file_name: 'poster-480p.png',
                    name: 'poster-480p',
                    path: '/poster/conversions/480p/poster-480p.png',
                    disk: config('media.disk'),
                ),
            ])
        );
    }
}
