<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

use FFMpeg\Coordinate\AspectRatio;
use FFMpeg\Coordinate\Dimension;
use ProtoneMedia\LaravelFFMpeg\FFMpeg\FFProbe;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class Video implements HasDimension
{
    public static function dimension(string $path): ?Dimension
    {
        $ffprobe = FFProbe::create([
            'ffmpeg.binaries' => config('laravel-ffmpeg.ffmpeg.binaries'),
            'ffprobe.binaries' => config('laravel-ffmpeg.ffprobe.binaries'),
        ]);

        $stream = $ffprobe
            ->streams($path)
            ->videos()
            ->first();

        if (! $stream) {
            return null;
        }

        $dimension = $stream->getDimensions();

        /** @var int */
        $rotation = data_get($stream->get('side_data_list'), '0.rotation', 0);

        if ($rotation && $rotation % 90 === 0 && $rotation % 180 !== 0) {
            return new Dimension($dimension->getHeight(), $dimension->getWidth());
        }

        return $dimension;
    }

    public static function rotation(string $path): ?int
    {
        $ffprobe = FFProbe::create([
            'ffmpeg.binaries' => config('laravel-ffmpeg.ffmpeg.binaries'),
            'ffprobe.binaries' => config('laravel-ffmpeg.ffprobe.binaries'),
        ]);

        $stream = $ffprobe
            ->streams($path)
            ->videos()
            ->first();

        if (! $stream) {
            return null;
        }

        /** @var int */
        $rotation = data_get($stream->get('side_data_list'), '0.rotation', 0);

        return $rotation;
    }

    public static function ratio(string $path, bool $forceStandards = true): ?AspectRatio
    {
        return static::dimension($path)?->getRatio($forceStandards);
    }

    public static function duration(string $path): float
    {
        return FFMpeg::open($path)->getDurationInMiliseconds();
    }
}
