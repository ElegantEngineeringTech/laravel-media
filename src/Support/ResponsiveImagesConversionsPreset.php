<?php

namespace Finller\Media\Support;

use Finller\Media\Enums\MediaType;
use Finller\Media\Jobs\OptimizedImageConversionJob;
use Finller\Media\MediaConversion;
use Finller\Media\Models\Media;
use Illuminate\Support\Collection;
use Spatie\Image\Manipulations;

class ResponsiveImagesConversionsPreset
{
    public static array $widths = [360, 720, 1080, 1440];

    /**
     * @return Collection<int, MediaConversion>
     */
    public static function get(Media $media): Collection
    {
        /**
         * @var Collection<int, MediaConversion> $conversions
         */
        $conversions = collect();

        if ($media->type !== MediaType::Image || ! $media->width) {
            return $conversions;
        }

        foreach (static::$widths as $width) {
            $name = (string) $width;
            if ($media->width > $width) {
                $conversions->push(new MediaConversion(
                    name: $name,
                    job: new OptimizedImageConversionJob(
                        media: $media,
                        conversion: $name,
                        width: $width,
                        fitMethod : Manipulations::FIT_MAX,
                    )
                ));
            }

        }

        return $conversions;
    }
}
