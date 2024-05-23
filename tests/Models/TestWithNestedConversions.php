<?php

namespace ElegantEngineeringTech\Media\Tests\Models;

use ElegantEngineeringTech\Media\Casts\GeneratedConversion;
use ElegantEngineeringTech\Media\Contracts\InteractWithMedia;
use ElegantEngineeringTech\Media\Enums\MediaType;
use ElegantEngineeringTech\Media\Jobs\OptimizedImageConversionJob;
use ElegantEngineeringTech\Media\MediaConversion;
use ElegantEngineeringTech\Media\Traits\HasMedia;
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
