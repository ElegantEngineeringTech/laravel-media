<?php

namespace Finller\Media\Support;

use FFMpeg\Coordinate\TimeCode;
use Finller\Media\Jobs\VideoPosterConversionJob;
use Finller\Media\MediaConversion;
use Finller\Media\Models\Media;
use Illuminate\Support\Collection;
use Spatie\Image\Enums\Fit;

class VideoPosterConversionPreset
{
    /**
     * @return Collection<int, MediaConversion>
     */
    public static function get(
        Media $media,
        bool $withResponsiveImages = false,
        string $extension = 'jpg',
        bool $sync = false,
        int|string|TimeCode $seconds = 0,
        ?int $width = null,
        ?int $height = null,
        Fit $fit = Fit::Max
    ): Collection {
        /**
         * @var Collection<int, MediaConversion> $conversions
         */
        $conversions = collect();

        $conversions->push(new MediaConversion(
            name: 'poster',
            sync: $sync,
            job: new VideoPosterConversionJob(
                media: $media,
                conversion: 'poster',
                seconds: $seconds,
                width: $width,
                height: $height,
                fit: $fit,
                fileName: "{$media->name}.{$extension}"
            ),
            conversions: $withResponsiveImages ?
                ResponsiveImagesConversionsPreset::get($media) :
                collect()
        ));

        return $conversions;
    }
}
