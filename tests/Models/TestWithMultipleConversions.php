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

class TestWithMultipleConversions extends Model
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
        ]);
    }

    /**
     * @return Collection<MediaConversion>
     */
    protected function registerMediaConversions(Media $media): Collection
    {

        if ($media->type === MediaType::Image) {
            return collect()
                ->push(new MediaConversion(
                    name: 'optimized',
                    job: new OptimizedImageConversionJob($media, 'optimized')
                ))
                ->push(new MediaConversion(
                    name: 'webp',
                    job: new OptimizedImageConversionJob(
                        $media,
                        conversion: 'webp',
                        fileName: "{$media->name}.webp"
                    )
                ));
        }

        return collect();
    }
}
