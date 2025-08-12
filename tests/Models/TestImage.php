<?php

declare(strict_types=1);

namespace Elegantly\Media\Tests\Models;

use Elegantly\Media\Concerns\HasMedia;
use Elegantly\Media\Converters\Image\MediaImageConverter;
use Elegantly\Media\MediaCollection;
use Elegantly\Media\MediaConversionDefinition;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

class TestImage extends Model
{
    use HasMedia;

    protected $table = 'tests';

    protected $guarded = [];

    public function registerMediaCollections(): Arrayable|iterable|null
    {
        return [
            new MediaCollection(
                name: 'images',
                conversions: [
                    new MediaConversionDefinition(
                        name: 'small',
                        queued: false,
                        immediate: true,
                        converter: fn ($media) => new MediaImageConverter(
                            media: $media,
                            filename: "{$media->name}.jpg"
                        )
                    ),
                ]
            ),
            new MediaCollection(
                name: 'nested-images',
                conversions: [
                    new MediaConversionDefinition(
                        name: 'small',
                        queued: false,
                        immediate: true,
                        converter: fn ($media) => new MediaImageConverter(
                            media: $media,
                            filename: "{$media->name}.jpg"
                        ),
                        conversions: [
                            new MediaConversionDefinition(
                                name: 'smaller',
                                queued: false,
                                immediate: true,
                                converter: fn ($media) => new MediaImageConverter(
                                    media: $media,
                                    filename: "{$media->name}.jpg",
                                    width: 100,
                                ),
                            ),
                        ]
                    ),
                ]
            ),
            new MediaCollection(
                name: 'delayed-nested-images',
                conversions: [
                    new MediaConversionDefinition(
                        name: 'small',
                        queued: false,
                        immediate: false,
                        converter: fn ($media) => new MediaImageConverter(
                            media: $media,
                            filename: "{$media->name}.jpg"
                        ),
                        conversions: [
                            new MediaConversionDefinition(
                                name: 'smaller',
                                queued: false,
                                immediate: false,
                                converter: fn ($media) => new MediaImageConverter(
                                    media: $media,
                                    filename: "{$media->name}.jpg",
                                    width: 100,
                                ),
                            ),
                        ]
                    ),
                ]
            ),
            new MediaCollection(
                name: 'delayed-immediate-nested-images',
                conversions: [
                    new MediaConversionDefinition(
                        name: 'small',
                        queued: false,
                        immediate: false,
                        converter: fn ($media) => new MediaImageConverter(
                            media: $media,
                            filename: "{$media->name}.jpg"
                        ),
                        conversions: [
                            new MediaConversionDefinition(
                                name: 'smaller',
                                queued: false,
                                immediate: true,
                                converter: fn ($media) => new MediaImageConverter(
                                    media: $media,
                                    filename: "{$media->name}.jpg",
                                    width: 100,
                                ),
                            ),
                        ]
                    ),
                ]
            ),
        ];
    }
}
