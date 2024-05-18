<?php

namespace Finller\Media\Tests\Models;

use Finller\Media\Contracts\InteractWithMedia;
use Finller\Media\Enums\MediaType;
use Finller\Media\MediaCollection;
use Finller\Media\Models\Media;
use Finller\Media\Support\VideoPosterConversionPreset;
use Finller\Media\Traits\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TestWithVideoPosterPreset extends Model implements InteractWithMedia
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
            return VideoPosterConversionPreset::get($media, withResponsiveImages: true);
        }

        return collect();
    }
}
