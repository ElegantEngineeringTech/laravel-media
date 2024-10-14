<?php

namespace Elegantly\Media\Tests\Models;

use Elegantly\Media\Concerns\HasMedia;
use Elegantly\Media\Definitions\MediaConversionImage;
use Elegantly\Media\Definitions\MediaConversionPoster;
use Elegantly\Media\Definitions\MediaConversionVideo;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Helpers\File;
use Elegantly\Media\MediaCollection;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

class Test extends Model
{
    use HasMedia;

    protected $table = 'tests';

    protected $guarded = [];

    public function registerMediaCollections(): Arrayable|iterable|null
    {
        return [
            new MediaCollection(
                name: 'files',
                single: false,
                public: false,
            ),
            new MediaCollection(
                name: 'single',
                single: true,
                public: true,
            ),
            new MediaCollection(
                name: 'fallback',
                single: true,
                public: true,
                fallback: fn () => 'fallback-value'
            ),
            new MediaCollection(
                name: 'transform',
                single: false,
                public: true,
                transform: function ($file) {
                    $path = $file->getRealPath();
                    $type = File::type($path);

                    if ($type === MediaType::Image) {

                        Image::load($path)
                            ->fit(Fit::Crop, 500, 500)
                            ->optimize()
                            ->save();

                    }

                    return $file;
                }
            ),
            new MediaCollection(
                name: 'conversions',
                single: false,
                public: false,
                conversions: [
                    new MediaConversionPoster(
                        name: 'poster',
                        queued: false,
                        conversions: [
                            new MediaConversionImage(
                                name: '360',
                                width: 360,
                                queued: false,
                            ),
                            new MediaConversionImage(
                                name: 'delayed',
                                immediate: false,
                                queued: false,
                            ),
                        ]
                    ),
                    new MediaConversionVideo(
                        name: 'small',
                        queued: true,
                        width: 100,
                    ),
                    new MediaConversionVideo(
                        name: 'delayed',
                        immediate: false,
                        queued: false,
                    ),
                ]
            ),
        ];
    }
}
