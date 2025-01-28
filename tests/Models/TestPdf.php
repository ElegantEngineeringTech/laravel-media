<?php

declare(strict_types=1);

namespace Elegantly\Media\Tests\Models;

use Elegantly\Media\Concerns\HasMedia;
use Elegantly\Media\Definitions\MediaConversionPdfPreview;
use Elegantly\Media\MediaCollection;
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
                    new MediaConversionPdfPreview(
                        name: 'preview',
                        queued: false,
                        width: 100,
                    ),
                ]
            ),
        ];
    }
}
