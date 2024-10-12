<?php

namespace Elegantly\Media\Database\Factories;

use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Models\MediaConversion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaConversion>
 */
class MediaConversionFactory extends Factory
{
    protected $model = MediaConversion::class;

    public function definition()
    {
        return [
            'conversion_name' => 'name',
            'state' => 'success',
            'state_set_at' => now(),
            'disk' => config('media.disk'),
            'path' => '{uuid}/conversions/name/fileName.jpg',
            'type' => MediaType::Image,
            'name' => 'fileName',
            'extension' => 'jpg',
            'file_name' => 'fileName.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 16,
            'height' => 9,
            'aspect_ratio' => 16 / 9,
            'average_color' => null,
            'size' => 800,
            'duration' => null,
            'metadata' => [],
        ];
    }
}
