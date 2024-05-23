<?php

namespace ElegantEngineeringTech\Media\Tests\Models;

use ElegantEngineeringTech\Media\Casts\GeneratedConversion;
use ElegantEngineeringTech\Media\Contracts\InteractWithMedia;
use ElegantEngineeringTech\Media\Enums\MediaType;
use ElegantEngineeringTech\Media\Jobs\VideoPosterConversionJob;
use ElegantEngineeringTech\Media\MediaCollection;
use ElegantEngineeringTech\Media\MediaConversion;
use ElegantEngineeringTech\Media\Support\ResponsiveImagesConversionsPreset;
use ElegantEngineeringTech\Media\Traits\HasMedia;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

class TestWithVideoConversions extends Model implements InteractWithMedia
{
    use HasMedia;

    protected $table = 'tests';

    protected $guarded = [];

    public function registerMediaCollections(): Arrayable|iterable|null
    {
        return collect([
            new MediaCollection(
                name: 'videos',
                single: false,
                public: false,
            ),
        ]);
    }

    public function registerMediaConversions($media): Arrayable|iterable|null
    {
        if ($media->type === MediaType::Video) {
            return [
                new MediaConversion(
                    conversionName: 'poster',
                    job: new VideoPosterConversionJob(
                        media: $media,
                        seconds: 0,
                        fileName: "{$media->name}.jpg",
                    ),
                    conversions: function (GeneratedConversion $generatedConversion) use ($media) {
                        return ResponsiveImagesConversionsPreset::make(
                            media: $media,
                            generatedConversion: $generatedConversion
                        );
                    }
                ),
            ];
        }

        return [];
    }
}
