<?php

namespace Finller\LaravelMedia\Tests\Models;

use Finller\LaravelMedia\Enums\MediaType;
use Finller\LaravelMedia\Jobs\OptimizedImageConversionJob;
use Finller\LaravelMedia\Media;
use Finller\LaravelMedia\MediaCollection;
use Finller\LaravelMedia\MediaConversion;
use Finller\LaravelMedia\Traits\HasMedia;
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
