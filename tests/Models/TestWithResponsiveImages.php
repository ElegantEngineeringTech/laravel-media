<?php

namespace ElegantEngineeringTech\Media\Tests\Models;

use ElegantEngineeringTech\Media\Contracts\InteractWithMedia;
use ElegantEngineeringTech\Media\Enums\MediaType;
use ElegantEngineeringTech\Media\MediaCollection;
use ElegantEngineeringTech\Media\Support\ResponsiveImagesConversionsPreset;
use ElegantEngineeringTech\Media\Traits\HasMedia;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

class TestWithResponsiveImages extends Model implements InteractWithMedia
{
    use HasMedia;

    protected $table = 'tests';

    protected $guarded = [];

    public function registerMediaCollections(): Arrayable|iterable|null
    {
        return [
            new MediaCollection(
                name: 'images',
                single: false,
                public: false,
                acceptedMimeTypes: ['image/*']
            ),
        ];
    }

    public function registerMediaConversions($media): Arrayable|iterable|null
    {
        if ($media->type === MediaType::Image) {
            return ResponsiveImagesConversionsPreset::make(
                media: $media,
            );
        }

        return collect();
    }
}
