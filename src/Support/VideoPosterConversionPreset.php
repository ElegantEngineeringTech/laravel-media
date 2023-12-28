<?php

namespace Finller\Media\Support;

use Finller\Media\Jobs\VideoPosterConversionJob;
use Finller\Media\MediaConversion;
use Finller\Media\Models\Media;
use Illuminate\Support\Collection;

class VideoPosterConversionPreset
{
    /**
     * @return Collection<int, MediaConversion>
     */
    public static function get(
        Media $media,
        bool $withResponsiveImages = false,
        string $extension = 'jpg'
    ): Collection {
        /**
         * @var Collection<int, MediaConversion> $conversions
         */
        $conversions = collect();

        $conversions->push(new MediaConversion(
            name: 'poster',
            job: new VideoPosterConversionJob(
                media: $media,
                conversion: 'poster',
                fileName: "{$media->name}.{$extension}"
            ),
            conversions: $withResponsiveImages ?
                ResponsiveImagesConversionsPreset::get($media) :
                null
        ));

        return $conversions;
    }
}
