<?php

namespace Finller\Media\Tests\Models;

use Finller\Media\Contracts\InteractWithMedia;
use Finller\Media\Enums\MediaType;
use Finller\Media\Jobs\OptimizedImageConversionJob;
use Finller\Media\MediaConversion;
use Finller\Media\Models\Media;
use Finller\Media\Traits\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TestWithNestedConversions extends Model implements InteractWithMedia
{
    use HasMedia;

    protected $table = 'tests';

    protected $guarded = [];

    public function registerMediaConversions(Media $media): Collection
    {

        if ($media->type === MediaType::Image) {
            return collect()
                ->push(new MediaConversion(
                    name: 'optimized',
                    job: new OptimizedImageConversionJob($media, 'optimized'),
                    conversions: collect()
                        ->push(new MediaConversion(
                            name: 'webp',
                            job: new OptimizedImageConversionJob($media, 'webp', fileName: "{$media->name}.webp")
                        ))
                ));
        }

        return collect();
    }
}
