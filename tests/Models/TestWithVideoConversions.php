<?php

namespace Finller\Media\Tests\Models;

use Finller\Media\Enums\MediaType;
use Finller\Media\Jobs\VideoPosterConversionJob;
use Finller\Media\MediaCollection;
use Finller\Media\MediaConversion;
use Finller\Media\Models\Media;
use Finller\Media\Traits\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TestWithVideoConversions extends Model
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
                name: 'videos',
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
        $conversions = collect();

        if ($media->type === MediaType::Video) {
            $conversions
                ->push(new MediaConversion(
                    name: 'poster',
                    job: new VideoPosterConversionJob(
                        media: $media,
                        conversion: 'poster',
                        seconds: 0,
                        fileName: "{$media->name}.jpg"
                    )
                ));
        }

        return $conversions;
    }
}
