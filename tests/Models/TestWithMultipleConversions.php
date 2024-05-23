<?php

namespace ElegantEngineeringTech\Media\Tests\Models;

use ElegantEngineeringTech\Media\Contracts\InteractWithMedia;
use ElegantEngineeringTech\Media\Enums\MediaType;
use ElegantEngineeringTech\Media\Jobs\OptimizedImageConversionJob;
use ElegantEngineeringTech\Media\MediaCollection;
use ElegantEngineeringTech\Media\MediaConversion;
use ElegantEngineeringTech\Media\Traits\HasMedia;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

class TestWithMultipleConversions extends Model implements InteractWithMedia
{
    use HasMedia;

    protected $table = 'tests';

    protected $guarded = [];

    public function registerMediaCollections(): Arrayable|iterable|null
    {
        return collect([
            new MediaCollection(
                name: 'files',
                single: false,
                public: false,
            ),
        ]);
    }

    public function registerMediaConversions($media): Arrayable|iterable|null
    {

        if ($media->type === MediaType::Image) {
            return [
                new MediaConversion(
                    conversionName: 'optimized',
                    job: new OptimizedImageConversionJob(
                        media: $media,
                    )
                ),
                new MediaConversion(
                    conversionName: 'webp',
                    job: new OptimizedImageConversionJob(
                        media: $media,
                        fileName: "{$media->name}.webp"
                    )
                ),
            ];
        }

        return collect();
    }
}
