<?php

namespace Elegantly\Media\Support;

use Elegantly\Media\Casts\GeneratedConversion;
use Elegantly\Media\Jobs\OptimizedVideoConversionJob;
use Elegantly\Media\MediaConversion;
use Elegantly\Media\Models\Media;
use FFMpeg\Filters\Video\ResizeFilter;
use FFMpeg\Format\FormatInterface;
use FFMpeg\Format\Video\X264;

class ResponsiveVideosConversionsPreset
{
    const DEFAULT_WIDTH = [360, 720, 1080, 1440];

    /**
     * @return MediaConversion[]
     */
    public static function make(
        Media $media,
        ?GeneratedConversion $generatedConversion,
        ?string $queue,
        ?FormatInterface $format = new X264,
        ?string $fitMethod = ResizeFilter::RESIZEMODE_FIT,
        ?bool $forceStandards = false,
        array $widths = ResponsiveImagesConversionsPreset::DEFAULT_WIDTH,
    ): array {

        $conversions = [];

        $baseName = $generatedConversion?->name ?? $media->name;

        foreach (static::getWidths($widths, $media, $generatedConversion) as $width) {

            $name = (string) $width;

            $conversions[] = new MediaConversion(
                conversionName: $name,
                job: new OptimizedVideoConversionJob(
                    media: $media,
                    queue: $queue,
                    width: $width,
                    format: $format,
                    fitMethod: $fitMethod,
                    forceStandards: $forceStandards,
                    fileName: "{$baseName}-{$name}.mp4"
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
