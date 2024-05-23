<?php

namespace Finller\Media\Tests\Models;

use Finller\Media\Casts\GeneratedConversion;
use Finller\Media\Contracts\InteractWithMedia;
use Finller\Media\Enums\MediaType;
use Finller\Media\Jobs\OptimizedImageConversionJob;
use Finller\Media\MediaConversion;
use Finller\Media\Traits\HasMedia;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

class TestWithNestedConversions extends Model implements InteractWithMedia
{
    use HasMedia;

    protected $table = 'tests';

    protected $guarded = [];

    public function registerMediaConversions($media): Arrayable|iterable|null
    {

        if ($media->type === MediaType::Image) {
            return [
                new MediaConversion(
                    conversionName: 'optimized',
                    job: new OptimizedImageConversionJob(
                        media: $media,
                        fileName: 'optimized.jpg'
                    ),
                    conversions: fn (GeneratedConversion $generatedConversion) => [
                        new MediaConversion(
                            conversionName: 'webp',
                            job: new OptimizedImageConversionJob(
                                media: $media,
                                fileName: "{$generatedConversion->name}.webp" // expected to be optimized.webp
                            ),
                        ),
                    ]
                ),
                new MediaConversion(
                    conversionName: '360',
                    job: new OptimizedImageConversionJob(
                        media: $media,
                        width: 360,
                        fileName: '360.jpg'
                    ),
                ),
            ];
        }

        return [];
    }
}
