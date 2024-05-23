<?php

namespace ElegantEngineeringTech\Media\Support;

use ElegantEngineeringTech\Media\Casts\GeneratedConversion;
use ElegantEngineeringTech\Media\Jobs\OptimizedImageConversionJob;
use ElegantEngineeringTech\Media\MediaConversion;
use ElegantEngineeringTech\Media\Models\Media;

class ResponsiveImagesConversionsPreset
{
    const DEFAULT_WIDTH = [360, 720, 1080, 1440];

    /**
     * @return MediaConversion[]
     */
    public static function make(
        Media $media,
        ?GeneratedConversion $generatedConversion = null,
        string $extension = 'jpg',
        ?string $queue = null,
        array $widths = ResponsiveImagesConversionsPreset::DEFAULT_WIDTH,
    ): array {

        $conversions = [];

        $baseName = $generatedConversion?->name ?? $media->name;

        foreach (static::getWidths($widths, $media, $generatedConversion) as $width) {

            $name = (string) $width;

            $conversions[] = new MediaConversion(
                conversionName: $name,
                job: new OptimizedImageConversionJob(
                    media: $media,
                    queue: $queue,
                    width: $width,
                    fileName: "{$baseName}-{$name}.{$extension}"
                )
            );
        }

        return $conversions;
    }

    public static function getWidths(
        array $widths,
        Media $media,
        ?GeneratedConversion $generatedConversion = null,
    ): array {
        return array_filter($widths, fn (int $width) => ($generatedConversion?->width ?? $media->width) >= $width);
    }
}
