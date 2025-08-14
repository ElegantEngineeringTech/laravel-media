<?php

declare(strict_types=1);

namespace Elegantly\Media\Tests\Models;

use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Helpers\File;
use Elegantly\Media\MediaCollection;
use Illuminate\Contracts\Support\Arrayable;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

class TestCollections extends Test
{
    public function registerMediaCollections(): Arrayable|iterable|null
    {
        return [
            new MediaCollection(
                name: 'multiple',
                single: false,
            ),
            new MediaCollection(
                name: 'single',
                single: true,
            ),
            new MediaCollection(
                name: 'fallback',
                single: false,
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
        ];
    }
}
