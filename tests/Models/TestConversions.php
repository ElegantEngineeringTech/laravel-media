<?php

declare(strict_types=1);

namespace Elegantly\Media\Tests\Models;

use Elegantly\Media\Concerns\HasMedia;
use Elegantly\Media\Converters\Image\MediaImageConverter;
use Elegantly\Media\MediaCollection;
use Elegantly\Media\MediaConversionDefinition;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

class TestConversions extends Model
{
    use HasMedia;

    protected $table = 'tests';

    protected $guarded = [];

    public function registerMediaCollections(): Arrayable|iterable|null
    {
        return [
            new MediaCollection(
                name: 'multiple',
                conversions: [
                    new MediaConversionDefinition(
                        name: 'foo',
                        queued: false,
                        immediate: false,
                        converter: fn ($media) => new MediaImageConverter(
                            media: $media,
                            filename: "{$media->name}.jpg"
                        )
                    ),
                    new MediaConversionDefinition(
                        name: 'bar',
                        queued: false,
                        immediate: false,
                        converter: fn ($media) => new MediaImageConverter(
                            media: $media,
                            filename: "{$media->name}.jpg"
                        )
                    ),
                ]
            ),
            new MediaCollection(
                name: 'simple',
                conversions: [
                    new MediaConversionDefinition(
                        name: 'small',
                        queued: false,
                        immediate: false,
                        converter: fn ($media) => new MediaImageConverter(
                            media: $media,
                            filename: "{$media->name}.jpg"
                        )
                    ),
                ]
            ),
            new MediaCollection(
                name: 'simple-immediate',
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
                name: 'simple-queued',
                conversions: [
                    new MediaConversionDefinition(
                        name: 'small',
                        queued: true,
                        immediate: false,
                        converter: fn ($media) => new MediaImageConverter(
                            media: $media,
                            filename: "{$media->name}.jpg"
                        )
                    ),
                ]
            ),
            new MediaCollection(
                name: 'simple-immediate-queued',
                conversions: [
                    new MediaConversionDefinition(
                        name: 'small',
                        queued: true,
                        immediate: true,
                        converter: fn ($media) => new MediaImageConverter(
                            media: $media,
                            filename: "{$media->name}.jpg"
                        )
                    ),
                ]
            ),
            new MediaCollection(
                name: 'immediate-nested-immediate',
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
                name: 'immediate-nested-immediate-queued',
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
                                queued: true,
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
                name: 'nested',
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
                            new MediaConversionDefinition(
                                name: 'immediate-smaller',
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
                name: 'nested-immediate',
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
