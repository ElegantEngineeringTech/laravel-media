<?php

namespace Finller\LaravelMedia\Helpers;

use FFMpeg\Coordinate\AspectRatio;
use FFMpeg\Coordinate\Dimension;

interface HasDimension
{
    public static function dimension(string $path): Dimension;

    public static function ratio(string $path, bool $forceStandards = true): AspectRatio;
}
