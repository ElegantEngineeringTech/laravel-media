<?php

namespace Elegantly\Media\Tests\Models;

use Elegantly\Media\Contracts\InteractWithMedia;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\MediaCollection;
use Elegantly\Media\Support\ResponsiveImagesConversionsPreset;
use Elegantly\Media\Traits\HasMedia;
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
