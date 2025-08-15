<?php

declare(strict_types=1);

namespace Elegantly\Media\Tests\Models;

use Elegantly\Media\Converters\Audio\MediaAacConverter;
use Elegantly\Media\Converters\Audio\MediaMp3Converter;
use Elegantly\Media\Converters\Audio\MediaWavConverter;
use Elegantly\Media\Converters\Image\MediaImageConverter;
use Elegantly\Media\Converters\Image\MediaImagePlaceholderConverter;
use Elegantly\Media\Converters\Pdf\MediaPdfToImageConverter;
use Elegantly\Media\Converters\Video\MediaFrameConverter;
use Elegantly\Media\Converters\Video\MediaMp4Converter;
use Elegantly\Media\MediaCollection;
use Elegantly\Media\MediaConversionDefinition;
use Illuminate\Contracts\Support\Arrayable;
use Spatie\Image\Enums\Fit;

class TestConverters extends Test
{
    public function registerMediaCollections(): Arrayable|iterable|null
    {
        return [
            new MediaCollection(
                name: config('media.default_collection_name'),
                conversions: [
                    new MediaConversionDefinition(
                        name: 'jpg',
                        immediate: false,
                        queued: false,
                        converter: fn ($media) => new MediaImageConverter(
                            media: $media,
                            filename: "{$media->name}.jpg",
                            width: 10,
                            height: 10,
                            fit: Fit::Crop,
                        )
                    ),
                    new MediaConversionDefinition(
                        name: 'mp4',
                        immediate: false,
                        queued: false,
                        converter: fn ($media) => new MediaMp4Converter(
                            media: $media,
                            filename: "{$media->name}.mp4",
                            width: 10,
                        )
                    ),
                    new MediaConversionDefinition(
                        name: 'mp3',
                        immediate: false,
                        queued: false,
                        converter: fn ($media) => new MediaMp3Converter(
                            media: $media,
                            filename: "{$media->name}.mp3",
                        )
                    ),
                    new MediaConversionDefinition(
                        name: 'wav',
                        immediate: false,
                        queued: false,
                        converter: fn ($media) => new MediaWavConverter(
                            media: $media,
                            filename: "{$media->name}.wav",
                        )
                    ),
                    new MediaConversionDefinition(
                        name: 'aac',
                        immediate: false,
                        queued: false,
                        converter: fn ($media) => new MediaAacConverter(
                            media: $media,
                            filename: "{$media->name}.m4a",
                        )
                    ),
                    new MediaConversionDefinition(
                        name: 'pdf',
                        immediate: false,
                        queued: false,
                        converter: fn ($media) => new MediaPdfToImageConverter(
                            media: $media,
                            filename: "{$media->name}.jpg",
                            width: 10,
                            height: 10,
                            fit: Fit::Crop,
                        )
                    ),
                    new MediaConversionDefinition(
                        name: 'svg',
                        immediate: false,
                        queued: false,
                        converter: fn ($media) => new MediaImagePlaceholderConverter(
                            media: $media,
                        )
                    ),
                    new MediaConversionDefinition(
                        name: 'frame',
                        immediate: false,
                        queued: false,
                        converter: fn ($media) => new MediaFrameConverter(
                            media: $media,
                            filename: "{$media->name}.jpg",
                            width: 10,
                        )
                    ),
                ],
            ),
        ];
    }
}
