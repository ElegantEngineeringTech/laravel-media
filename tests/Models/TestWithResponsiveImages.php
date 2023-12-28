<?php

namespace Finller\Media\Tests\Models;

use Finller\Media\Enums\MediaType;
use Finller\Media\MediaCollection;
use Finller\Media\MediaConversion;
use Finller\Media\Models\Media;
use Finller\Media\Support\ResponsiveImagesConversionsPreset;
use Finller\Media\Traits\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TestWithResponsiveImages extends Model
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
                name: 'images',
                single: false,
                public: false,
                acceptedMimeTypes: ['image/*']
            ),
        ]);
    }

    /**
     * @return Collection<MediaConversion>
     */
    protected function registerMediaConversions(Media $media): Collection
    {
        if ($media->type === MediaType::Image) {
            return ResponsiveImagesConversionsPreset::get($media);
        }

        return collect();
    }
}
