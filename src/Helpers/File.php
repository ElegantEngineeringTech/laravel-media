<?php

namespace Elegantly\Media\Helpers;

use Elegantly\Media\Enums\MediaType;
use FFMpeg\Coordinate\Dimension;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\File as HttpFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File as SupportFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class File
{
    public static function name(string|HttpFile|UploadedFile $file): ?string
    {
        if ($file instanceof UploadedFile) {
            return SupportFile::name($file->getClientOriginalName());
        }

        if ($file instanceof HttpFile) {
            return SupportFile::name($file->getPathname());
        }

        return SupportFile::name($file);
    }

    public static function mimeType(string|HttpFile|UploadedFile $file): ?string
    {
        if ($file instanceof UploadedFile) {
            return $file->getMimeType() ?? $file->getClientMimeType();
        }
        if ($file instanceof HttpFile) {
            return $file->getMimeType();
        }

        return SupportFile::mimeType($file) ?: null;
    }

    public static function extension(string|HttpFile|UploadedFile $file): ?string
    {
        if ($file instanceof UploadedFile) {
            return $file->guessExtension() ?? $file->getClientOriginalExtension();
        }

        if ($file instanceof HttpFile) {
            return $file->guessExtension() ?? $file->getExtension();
        }

        return SupportFile::extension($file) ?: SupportFile::guessExtension($file);
    }

    public static function type(string $path): MediaType
    {
        return MediaType::tryFromStreams($path);
    }

    public static function duration(string $path): ?float
    {
        if (static::type($path) === MediaType::Video) {
            $disk = Storage::build([
                'driver' => 'local',
                'root' => SupportFile::dirname($path),
            ]);

            return FFMpeg::fromDisk($disk)->open(SupportFile::basename($path))->getDurationInMiliseconds();
        }

        return null;
    }

    public static function dimension(string $path): ?Dimension
    {
        $type = static::type($path);

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

    public static function extractFilename(string|HttpFile $file, ?string $name = null): string
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
