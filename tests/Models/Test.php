<?php

namespace Elegantly\Media\Tests\Models;

use Elegantly\Media\Contracts\InteractWithMedia;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Jobs\OptimizedImageConversionJob;
use Elegantly\Media\MediaCollection;
use Elegantly\Media\MediaConversion;
use Elegantly\Media\Traits\HasMedia;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;

class Test extends Model implements InteractWithMedia
{
    use HasMedia;

    protected $table = 'tests';

    protected $guarded = [];

    public function registerMediaCollections(): Arrayable|iterable|null
    {
        return [
            new MediaCollection(
                name: 'files',
                single: false,
                public: false,
            ),
            new MediaCollection(
                name: 'avatar',
                single: true,
                public: true,
            ),
            new MediaCollection(
                name: 'fallback',
                single: true,
                public: true,
                fallback: fn () => 'fallback-value'
            ),
        ];
    }

    public function registerMediaConversions($media): Arrayable|iterable|null
    {
        $conversions = collect();

        if ($media->type === MediaType::Image) {
            $conversions->push(new MediaConversion(
                conversionName: 'optimized',
                job: new OptimizedImageConversionJob(
                    media: $media,
                )
            ));

            if ($media->collection_name === 'avatar') {
                $conversions->push(new MediaConversion(
                    conversionName: 'small',
                    job: new OptimizedImageConversionJob(
                        media: $media,
                        width: 5,
                        height: 5,
                        fit: Fit::Crop
                    )
                ));
            }
        }

        return $conversions;
    }
}
