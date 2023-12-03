<?php

namespace Finller\Media\Tests\Models;

use Finller\Media\Enums\MediaType;
use Finller\Media\Jobs\OptimizedImageConversionJob;
use Finller\Media\MediaConversion;
use Finller\Media\Models\Media;
use Finller\Media\Traits\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TestWithNestedConversions extends Model
{
    use HasMedia;

    protected $table = 'tests';

    protected $guarded = [];

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
                    job: new OptimizedImageConversionJob($media, 'optimized'),
                    conversions: collect()
                        ->push(new MediaConversion(
                            name: 'webp',
                            job: new OptimizedImageConversionJob($media, 'webp', fileName: "{$media->name}.webp")
                        ))
                ));
        }

        return $conversions;
    }
}
