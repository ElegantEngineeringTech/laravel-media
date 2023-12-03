<?php

namespace Finller\Media\Tests\Models;

use Finller\Media\Enums\MediaType;
use Finller\Media\Jobs\OptimizedImageConversionJob;
use Finller\Media\MediaCollection;
use Finller\Media\MediaConversion;
use Finller\Media\Models\Media;
use Finller\Media\Traits\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Image\Manipulations;

class Test extends Model
{
    use HasMedia;

    protected $table = 'tests';

    protected $guarded = [];

    /**
     * @return Collection<MediaCollection>
     */
    protected function registerMediaCollections(): Collection
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
        ]);
    }

    /**
     * @return Collection<MediaConversion>
     */
    protected function registerMediaConversions(Media $media): Collection
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
                        fitMethod: Manipulations::FIT_CROP
                    )
                ));
            }
        }

        return $conversions;
    }
}
