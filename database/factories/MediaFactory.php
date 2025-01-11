<?php

declare(strict_types=1);

namespace Elegantly\Media\Database\Factories;

use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
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
            'path' => '{uuid}/empty.jpg',
            'type' => MediaType::Image,
            'collection_name' => config('media.default_collection_name'),
            'disk' => config('media.disk'),
        ];
    }

    public function withPoster(): static
    {
        return $this->has(
            MediaConversion::factory()
                ->state(fn ($attributes) => [
                    'conversion_name' => 'poster',
                    'disk' => $attributes['disk'],
                    'path' => '{uuid}/conversions/poster/poster.jpg',
                    'type' => MediaType::Image,
                    'name' => 'poster',
                    'extension' => 'jpg',
                    'file_name' => 'poster.jpg',
                    'mime_type' => 'image/jpeg',
                    'width' => $attributes['width'] ?? null,
                    'height' => $attributes['height'] ?? null,
                    'aspect_ratio' => $attributes['aspect_ratio'] ?? null,
                ]),
            'conversions'
        );
    }
}
