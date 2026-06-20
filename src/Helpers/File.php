<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

use Elegantly\Media\Enums\MediaType;
use Illuminate\Http\File as HttpFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File as SupportFile;
use Illuminate\Support\Str;

class File
{
    /**
     * @return ($path is UploadedFile ? UploadedFile : HttpFile)
     */
    public static function asFile(string|HttpFile|UploadedFile $path): HttpFile|UploadedFile
    {
        if ($path instanceof HttpFile) {
            return $path;
        }

        if ($path instanceof UploadedFile) {
            return $path;
        }

        return new HttpFile($path, false);
    }

    public static function name(string|HttpFile|UploadedFile $file): ?string
    {
        $file = static::asFile($file);

        if ($file instanceof UploadedFile) {
            return SupportFile::name($file->getClientOriginalName());
        }

        return SupportFile::name($file->getPathname());
    }

    /**
     * Depending on the operating system, the guessed mimType for m3u8 files
     * can be unrelable (audio/x-mpegurl, text/plain...)
     */
    public static function mimeType(string|HttpFile|UploadedFile $file): ?string
    {
        $file = static::asFile($file);

        $extension = static::extension($file);

        if ($extension === 'm3u8') {
            $lines = SupportFile::lines($file->getPathname());
            $isHls = (bool) $lines->first(fn ($line) => is_string($line) && str_starts_with($line, '#EXT-X-'));

            return $isHls ? 'application/vnd.apple.mpegurl' : 'audio/x-mpegurl';
        }

        if ($file instanceof UploadedFile) {
            return $file->getMimeType() ?? $file->getClientMimeType();
        }

        return $file->getMimeType();

    }

    public static function extension(string|HttpFile|UploadedFile $file): ?string
    {
        $file = static::asFile($file);

        if ($file instanceof UploadedFile) {
            return $file->getClientOriginalExtension() ?: $file->guessExtension();
        }

        return $file->getExtension() ?: $file->guessExtension();
    }

    public static function type(string $path): MediaType
    {
        return MediaType::guess($path);
    }

    /**
     * Duration in Ms
     */
    public static function duration(string $path): ?float
    {
        $type = static::type($path);

        return match ($type) {
            MediaType::Video => Video::duration($path),
            MediaType::Audio => Audio::duration($path),
            default => null,
        };

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
            separator: '_',
            dictionary: ['@' => 'at', '+' => '_']
        );
    }
}
