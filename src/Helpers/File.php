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
        $mimeType = match (true) {
            $file instanceof UploadedFile => $file->getMimeType() ?? $file->getClientMimeType(),
            $file instanceof HttpFile => $file->getMimeType(),
            default => SupportFile::mimeType($file) ?: null
        };

        if ($mimeType === 'text/plain') {

            return match (static::extension($file)) {
                'm3u8' => 'application/vnd.apple.mpegurl',
                default => $mimeType,
            };
        }

        return $mimeType;

    }

    public static function extension(string|HttpFile|UploadedFile $file): ?string
    {
        return match (true) {
            $file instanceof UploadedFile => $file->getClientOriginalExtension() ?: $file->guessExtension(),
            $file instanceof HttpFile => $file->getExtension() ?: $file->guessExtension(),
            default => SupportFile::extension($file) ?: SupportFile::guessExtension($file),
        };
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
