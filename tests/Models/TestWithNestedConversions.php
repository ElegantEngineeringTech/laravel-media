<?php

namespace Finller\LaravelMedia\Tests\Models;

use Finller\LaravelMedia\Enums\MediaType;
use Finller\LaravelMedia\Jobs\OptimizedImageConversionJob;
use Finller\LaravelMedia\Media;
use Finller\LaravelMedia\MediaConversion;
use Finller\LaravelMedia\Traits\HasMedia;
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
