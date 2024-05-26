<?php

namespace Elegantly\Media\Tests\Models;

use Elegantly\Media\Casts\GeneratedConversion;
use Elegantly\Media\Contracts\InteractWithMedia;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Jobs\VideoPosterConversionJob;
use Elegantly\Media\MediaCollection;
use Elegantly\Media\MediaConversion;
use Elegantly\Media\Support\ResponsiveImagesConversionsPreset;
use Elegantly\Media\Traits\HasMedia;
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
