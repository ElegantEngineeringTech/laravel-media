<?php

namespace Finller\Media\Tests\Models;

use Finller\Media\Contracts\InteractWithMedia;
use Finller\Media\Enums\MediaType;
use Finller\Media\Jobs\OptimizedImageConversionJob;
use Finller\Media\MediaCollection;
use Finller\Media\MediaConversion;
use Finller\Media\Traits\HasMedia;
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
        return collect([
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
        ]);
    }

    public function registerMediaConversions($media): Arrayable|iterable|null
    {
        $conversions = collect();

        if ($media->type === MediaType::Image) {
            $conversions
                ->push(new MediaConversion(
                    name: 'optimized',
                    job: new OptimizedImageConversionJob($media, 'optimized')
                ));

            if ($media->collection_name === 'avatar') {
                $conversions->push(new MediaConversion(
                    name: 'small',
                    job: new OptimizedImageConversionJob(
                        media: $media,
                        conversion: 'small',
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
