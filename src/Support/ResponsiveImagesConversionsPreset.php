<?php

namespace Finller\Media\Support;

use Finller\Media\Jobs\OptimizedImageConversionJob;
use Finller\Media\MediaConversion;
use Finller\Media\Models\Media;
use Illuminate\Support\Collection;
use Spatie\Image\Enums\Fit;

class ResponsiveImagesConversionsPreset
{
    public static array $widths = [360, 720, 1080, 1440];

    /**
     * @return Collection<int, MediaConversion>
     */
    public static function get(
        Media $media,
        string $extension = 'jpg',
        bool $sync = false,
    ): Collection {
        /**
         * @var Collection<int, MediaConversion> $conversions
         */
        $conversions = collect();

        foreach (static::getWidths($media) as $width) {
            $name = (string) $width;

            $conversions->push(new MediaConversion(
                name: $name,
                sync: $sync,
                job: new OptimizedImageConversionJob(
                    media: $media,
                    conversion: $name,
                    width: $width,
                    fit : Fit::Contain,
                    fileName: "{$media->name}-{$name}.{$extension}"
                )
            ));

        }

        return $conversions;
    }

    public static function getWidths(Media $media): array
    {
        return array_filter(static::$widths, fn (int $width) => $width <= $media->width);
    }
}
