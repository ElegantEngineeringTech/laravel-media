<?php

namespace Finller\LaravelMedia\Helpers;

use FFMpeg\Coordinate\AspectRatio;
use FFMpeg\Coordinate\Dimension;
use ProtoneMedia\LaravelFFMpeg\FFMpeg\FFProbe;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class Video implements HasDimension
{
    public static function dimension(string $path): Dimension
    {
        $file = FFProbe::create([
            'ffmpeg.binaries' => config('laravel-ffmpeg.ffmpeg.binaries'),
            'ffprobe.binaries' => config('laravel-ffmpeg.ffprobe.binaries'),
        ]);

        return $file
            ->streams($path)
            ->videos()
            ->first()
            ->getDimensions();
    }

    public static function ratio(string $path, bool $forceStandards = true): AspectRatio
    {
        return static::dimension($path)->getRatio($forceStandards);
    }

    public static function duration(string $path): float
    {
        return FFMpeg::open($path)->getDurationInMiliseconds();
    }
}
