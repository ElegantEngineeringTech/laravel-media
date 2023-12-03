<?php

namespace Finller\Media\Database\Factories;

use Finller\Media\Casts\GeneratedConversion;
use Finller\Media\Enums\MediaType;
use Finller\Media\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @template TModel of Media
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition()
    {
        return [
            'name' => 'empty',
            'file_name' => 'empty.jpg',
            'size' => 10,
            'path' => '/uuid/empty.jpg',
            'type' => MediaType::Image,
            'collection_name' => config('media.default_collection_name'),
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
            generated_conversions: collect([
                '480p' => new GeneratedConversion(
                    state: 'success',
                    type: MediaType::Image,
                    file_name: 'poster-480p.png',
                    name: 'poster-480p',
                    path: '/poster/generated_conversions/480p/poster-480p.png',
                    disk: config('media.disk'),
                ),
            ])
        );
    }
}
