<?php

namespace Finller\Media\Tests\Models;

use Finller\Media\Contracts\InteractWithMedia;
use Finller\Media\Enums\MediaType;
use Finller\Media\Jobs\VideoPosterConversionJob;
use Finller\Media\MediaCollection;
use Finller\Media\MediaConversion;
use Finller\Media\Models\Media;
use Finller\Media\Traits\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TestWithVideoConversions extends Model implements InteractWithMedia
{
    use HasMedia;

    protected $table = 'tests';

    protected $guarded = [];

    public function registerMediaCollections(): Collection
    {
        return collect([
            new MediaCollection(
                name: 'videos',
                single: false,
                public: false,
            ),
        ]);
    }

    public function registerMediaConversions(Media $media): Collection
    {
        if ($media->type === MediaType::Video) {
            return collect()
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

        return collect();
    }
}
