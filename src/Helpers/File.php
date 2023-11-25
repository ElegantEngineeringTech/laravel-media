<?php

namespace Finller\LaravelMedia\Helpers;

use FFMpeg\Coordinate\Dimension;
use Finller\LaravelMedia\Enums\MediaType;

class File
{
    static function dimension(string $path, ?MediaType $type = null,  ?string $mime_type = null): ?Dimension
    {
        $type ??= MediaType::tryFromMimeType($mime_type ?? mime_content_type($path));

        return match ($type) {
            MediaType::Video => Video::dimension($path),
            MediaType::Image => Image::dimension($path),
            default => null
        };
    }
}
