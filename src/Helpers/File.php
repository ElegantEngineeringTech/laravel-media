<?php

namespace Finller\Media\Helpers;

use FFMpeg\Coordinate\Dimension;
use Finller\Media\Enums\MediaType;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\File as HttpFile;
use Illuminate\Support\Facades\File as SupportFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class File
{
    public static function type(string $path): MediaType
    {
        return MediaType::tryFromMimeType(SupportFile::mimeType($path));
    }

    public static function dimension(string $path, MediaType $type = null, string $mime_type = null): ?Dimension
    {
        $type ??= (MediaType::tryFromMimeType($mime_type) ?? static::type($path));

        return match ($type) {
            MediaType::Video => Video::dimension($path),
            MediaType::Image => Image::dimension($path),
            default => null
        };
    }

    public static function sanitizeFilename(string $fileName): string
    {
        return Str::slug(
            $fileName,
            dictionary: ['@' => 'at', '+' => '-']
        );
    }

    public static function extractFilename(string|HttpFile $file, string $name = null): string
    {
        $file = $file instanceof HttpFile ? $file : new HttpFile($file);

        $name = static::sanitizeFilename($name ?? SupportFile::name($file->getPathname()));
        $extension = $file->guessExtension();

        return "{$name}.{$extension}";
    }

    public static function makeTemporaryDisk(TemporaryDirectory $directory): Filesystem
    {
        return Storage::build([
            'driver' => 'local',
            'root' => $directory->path(),
        ]);
    }
}
