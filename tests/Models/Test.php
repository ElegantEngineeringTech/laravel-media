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
use Illuminate\Support\Str;

/**
 * @property ?string $uuid
 */
class Test extends Model
{
    use HasMedia;

    protected $table = 'tests';

    protected $guarded = [];

    /**
     * @return Collection<string, MediaCollection>
     */
    public function getMediaCollections(): Collection
    {
        return collect([
            'files' => new MediaCollection(
                single: false,
                public: false,
            )
        ]);
    }

    function getMediaConversions(Media $media): Collection
    {

        $conversions = collect();

        if ($media->type === MediaType::Image) {
            $conversions->push(
                new MediaConversion(
                    name: 'optimized',
                    job: new OptimizedImageConversionJob($media, 'optimized')
                )
            );
        }

        return $conversions->keyBy('name');
    }
}
