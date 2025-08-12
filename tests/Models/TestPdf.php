<?php

declare(strict_types=1);

namespace Elegantly\Media\Tests\Models;

use Elegantly\Media\Concerns\HasMedia;
use Elegantly\Media\Converters\Pdf\MediaPdfPreviewConverter;
use Elegantly\Media\MediaCollection;
use Elegantly\Media\MediaConversionDefinition;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

class TestPdf extends Model
{
    use HasMedia;

    protected $table = 'tests';

    protected $guarded = [];

    public function registerMediaCollections(): Arrayable|iterable|null
    {
        return [
            new MediaCollection(
                name: 'files',
                conversions: [
                    new MediaConversionDefinition(
                        name: 'preview',
                        queued: false,
                        converter: fn ($media) => new MediaPdfPreviewConverter(
                            media: $media,
                            filename: "{$media->name}.jpg",
                            width: 100,
                        )
                    ),
                ]
            ),
        ];
    }
}
