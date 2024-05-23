<?php

namespace ElegantEngineeringTech\Media\Tests\Models;

use ElegantEngineeringTech\Media\MediaCollection;
use FFMpeg\Filters\Video\ResizeFilter;
use FFMpeg\Format\Video\X264;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

class TestWithMediaTransformations extends Test
{
    public function registerMediaCollections(): Arrayable|iterable|null
    {
        return [
            new MediaCollection(
                name: 'avatar',
                single: true,
            ),
            new MediaCollection(
                name: 'video',
                single: true,
            ),
        ];
    }

    public function registerMediaTransformations($media, UploadedFile|File $file): UploadedFile|File
    {
        if ($media->collection_name === 'avatar') {
            Image::load($file->getRealPath())
                ->fit(Fit::Crop, 500, 500)
                ->optimize()
                ->save();
        } elseif ($media->collection_name === 'video') {
            // @phpstan-ignore-next-line
            FFMpeg::open($file)
                ->export()
                ->inFormat(new X264)
                ->resize(500, null, ResizeFilter::RESIZEMODE_FIT, false)
                ->save();
        }

        return $file;
    }
}
