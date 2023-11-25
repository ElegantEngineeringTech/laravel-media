<?php

namespace Finller\LaravelMedia\Helpers;

use FFMpeg\Coordinate\AspectRatio;
use FFMpeg\Coordinate\Dimension;

interface HasDimension
{
    static function dimension(string $path): Dimension;
    static function ratio(string $path, bool $forceStandards = true): AspectRatio;
}
