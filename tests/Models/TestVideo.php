<?php

declare(strict_types=1);

namespace Elegantly\Media\Tests\Models;

use Elegantly\Media\Concerns\HasMedia;
use Elegantly\Media\Converters\Video\MediaMp4Converter;
use Elegantly\Media\MediaCollection;
use Elegantly\Media\MediaConversionDefinition;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

class TestVideo extends Model
{
    use HasMedia;

    protected $table = 'tests';

    protected $guarded = [];

    public function registerMediaCollections(): Arrayable|iterable|null
    {
        return [
            new MediaCollection(
                name: 'queued',
                conversions: [
                    new MediaConversionDefinition(
                        name: 'small',
                        queued: true,
                        converter: fn ($media) => new MediaMp4Converter(
                            media: $media,
                            filename: "{$media->name}.mp4",
                            width: 100,
                        )
                    ),
                ]
            ),
            new MediaCollection(
                name: 'unqueued',
                conversions: [
                    new MediaConversionDefinition(
                        name: 'small',
                        queued: false,
                        converter: fn ($media) => new MediaMp4Converter(
                            media: $media,
                            filename: "{$media->name}.mp4",
                            width: 100,
                        )
                    ),
                ]
            ),
        ];
    }
}
