<?php

namespace Elegantly\Media\Database\Factories;

use Elegantly\Media\Casts\GeneratedConversion;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Models\Media;
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
        ];
    }

    public function withPoster(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'generated_conversions' => collect($attributes['generated_conversions'] ?? [])
                    ->put('poster', new GeneratedConversion(
                        state: 'success',
                        type: MediaType::Image,
                        file_name: 'poster.png',
                        name: 'poster',
                        path: '/uuid/poster/poster.png',
                        disk: $attributes['disk'],
                    )),
            ];
        });
    }

    public static function generatedConversion(?string $disk = null)
    {
        return new GeneratedConversion(
            state: 'success',
            type: MediaType::Image,
            file_name: 'poster.png',
            name: 'poster',
            path: '/poster/poster.png',
            disk: $disk ?? config('media.disk'),
            generated_conversions: collect([
                '480p' => new GeneratedConversion(
                    state: 'success',
                    type: MediaType::Image,
                    file_name: 'poster-480p.png',
                    name: 'poster-480p',
                    path: '/poster/generated_conversions/480p/poster-480p.png',
                    disk: $disk ?? config('media.disk'),
                ),
            ])
        );
    }
}
