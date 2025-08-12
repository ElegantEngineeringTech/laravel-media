<?php

declare(strict_types=1);

namespace Elegantly\Media\Tests\Models;

use Elegantly\Media\Concerns\HasMedia;
use Elegantly\Media\Converters\Image\MediaImageConverter;
use Elegantly\Media\Converters\Video\MediaMp4Converter;
use Elegantly\Media\Converters\Video\MediaPosterConverter;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Helpers\File;
use Elegantly\Media\MediaCollection;
use Elegantly\Media\MediaConversionDefinition;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

class Test extends Model
{
    use HasMedia;

    protected $table = 'tests';

    protected $guarded = [];

    public function registerMediaCollections(): Arrayable|iterable|null
    {
        return [
            new MediaCollection(
                name: 'files',
                single: false,
                public: false,
            ),
            new MediaCollection(
                name: 'single',
                single: true,
                public: true,
            ),
            new MediaCollection(
                name: 'fallback',
                single: true,
                public: true,
                fallback: fn () => 'fallback-value'
            ),
            new MediaCollection(
                name: 'transform',
                single: false,
                public: true,
                transform: function ($file) {
                    $path = $file->getRealPath();
                    $type = File::type($path);

                    if ($type === MediaType::Image) {

                        Image::load($path)
                            ->fit(Fit::Crop, 500, 500)
                            ->optimize()
                            ->save();

                    }

                    return $file;
                }
            ),
            new MediaCollection(
                name: 'conversions',
                single: false,
                public: false,
                conversions: [
                    new MediaConversionDefinition(
                        name: 'poster',
                        queued: false,
                        converter: fn ($media) => new MediaPosterConverter(
                            media: $media,
                            filename: "{$media->name}.jpg"
                        ),
                        conversions: [
                            new MediaConversionDefinition(
                                name: '360',
                                queued: false,
                                converter: fn ($media) => new MediaImageConverter(
                                    media: $media,
                                    filename: "{$media->name}.jpg",
                                    width: 360,
                                ),
                            ),
                            new MediaConversionDefinition(
                                name: 'delayed',
                                immediate: false,
                                queued: false,
                                converter: fn ($media) => new MediaImageConverter(
                                    media: $media,
                                    filename: "{$media->name}.jpg",
                                ),
                            ),
                        ]
                    ),
                    new MediaConversionDefinition(
                        name: 'small',
                        queued: true,
                        converter: fn ($media) => new MediaMp4Converter(
                            media: $media,
                            filename: "{$media->name}.mp4",
                            width: 100,
                        ),
                    ),
                    new MediaConversionDefinition(
                        name: 'delayed',
                        immediate: false,
                        queued: false,
                        converter: fn ($media) => new MediaMp4Converter(
                            media: $media,
                            filename: "{$media->name}.mp4",
                        ),
                    ),
                ]
            ),
            new MediaCollection(
                name: 'conversions-delayed',
                single: false,
                public: false,
                conversions: [
                    new MediaConversionDefinition(
                        name: 'poster',
                        queued: false,
                        immediate: false,
                        converter: fn ($media) => new MediaPosterConverter(
                            media: $media,
                            filename: "{$media->name}.jpg",
                        ),
                        conversions: [
                            new MediaConversionDefinition(
                                name: '360',
                                queued: false,
                                immediate: true,
                                converter: fn ($media) => new MediaImageConverter(
                                    media: $media,
                                    filename: "{$media->name}-360.jpg",
                                    width: 360,
                                ),
                            ),
                        ]
                    ),

                ]
            ),
        ];
    }
}
