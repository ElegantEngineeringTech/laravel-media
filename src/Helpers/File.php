<?php

declare(strict_types=1);

namespace Elegantly\Media\Helpers;

use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Exceptions\MediaFileStorageException;
use Illuminate\Http\File as HttpFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File as SupportFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Mime\MimeTypes;

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

    /**
     * The name of the file without its extension, or null when there is none (e.g. ".htaccess").
     */
    public static function name(string|HttpFile|UploadedFile $file): ?string
    {
        $file = static::asFile($file);

        $name = SupportFile::name(
            $file instanceof UploadedFile ? $file->getClientOriginalName() : $file->getPathname()
        );

        return $name === '' ? null : $name;
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

    /**
     * The extension is lowercased and only kept when it is strictly alphanumeric
     * (1 to 10 chars). Otherwise it is guessed from the file content, so a crafted
     * name can never inject unexpected characters into a stored path.
     */
    public static function extension(string|HttpFile|UploadedFile $file): ?string
    {
        $file = static::asFile($file);

        $extension = static::validExtension(
            $file instanceof UploadedFile ? $file->getClientOriginalExtension() : $file->getExtension()
        );

        if ($extension !== null) {
            return $extension;
        }

        $mimeType = $file->getMimeType();

        return $mimeType ? static::extensionFromMimeType($mimeType) : null;
    }

    /**
     * The lowercased extension when it is strictly alphanumeric (1 to 10 chars), null otherwise.
     */
    protected static function validExtension(string $extension): ?string
    {
        $extension = mb_strtolower($extension);

        return preg_match('/^[a-z0-9]{1,10}$/D', $extension) ? $extension : null;
    }

    /**
     * Sets the extension of a path or a file name, replacing the existing one or adding it when missing,
     * e.g. "videos/movie.mov" becomes "videos/movie.mp4" and "movie" becomes "movie.mp4".
     *
     * Only the last extension is replaced ("archive.tar.gz" becomes "archive.tar.zip") and
     * the leading dot of a dotfile is not an extension (".env" becomes ".env.txt").
     *
     * @param  null|string  $extension  With or without the leading dot. Null or an empty value removes the extension.
     */
    public static function withExtension(string $path, ?string $extension): string
    {
        $basename = basename($path);
        $directory = mb_substr($path, 0, (int) mb_strrpos($path, $basename));

        $dot = mb_strrpos($basename, '.');
        $name = $dot ? mb_substr($basename, 0, $dot) : $basename;

        $extension = mb_ltrim($extension ?? '', '.');

        return $extension === ''
            ? "{$directory}{$name}"
            : "{$directory}{$name}.{$extension}";
    }

    /**
     * The default extension of a MIME type, e.g. "image/jpeg" gives "jpg".
     * Parameters are ignored, so "text/plain; charset=utf-8" gives "txt".
     */
    public static function extensionFromMimeType(string $mimeType): ?string
    {
        $mimeType = mb_strtolower(mb_trim(Str::before($mimeType, ';')));

        if ($mimeType === '') {
            return null;
        }

        return MimeTypes::getDefault()->getExtensions($mimeType)[0] ?? null;
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

    /**
     * Sanitizes the name of a file, without its extension, e.g. "My Video" becomes "my_video".
     *
     * @param  string  $default  Returned as is when nothing is left of the name once sanitized, e.g. "🎉" or "!!!"
     */
    public static function sanitizeFilename(string $fileName, string $default = ''): string
    {
        $name = Str::slug(
            $fileName,
            separator: '_',
            dictionary: ['@' => 'at', '+' => '_']
        );

        return $name === '' ? $default : $name;
    }

    /**
     * Sanitizes the name of a file while keeping its extension, e.g. "My Video.MP4" becomes "my_video.mp4".
     *
     * The extension is lowercased and only kept when it is strictly alphanumeric (1 to 10 chars).
     * Otherwise it is sanitized as a part of the name, e.g. "foo.b@r" becomes "foob_at_r".
     *
     * @throws InvalidArgumentException When nothing is left of the name once sanitized, e.g. "🎉.png" or ".mp4"
     */
    public static function sanitizeBasename(string $basename): string
    {
        $basename = basename(mb_trim($basename));

        $extension = static::validExtension(pathinfo($basename, PATHINFO_EXTENSION));

        $name = static::sanitizeFilename(
            $extension === null ? $basename : pathinfo($basename, PATHINFO_FILENAME)
        );

        if ($name === '') {
            throw new InvalidArgumentException("The file name '{$basename}' is empty after sanitization.");
        }

        return static::withExtension($name, $extension);
    }

    /**
     * Moves a stored file to another path, on the same disk or on another one.
     *
     * @throws MediaFileStorageException When the destination already exists or the move fails
     */
    public static function move(
        string $disk,
        string $path,
        string $destinationDisk,
        string $destination,
    ): void {
        $source = Storage::disk($disk);
        $target = Storage::disk($destinationDisk);

        if ($target->exists($destination)) {
            throw MediaFileStorageException::moveTargetExists($path, $disk, $destination, $destinationDisk);
        }

        if ($disk === $destinationDisk) {

            if (! $source->move($path, $destination)) {
                throw MediaFileStorageException::moveFailed($path, $disk, $destination, $destinationDisk);
            }

        } else {
            $stream = $source->readStream($path);
            $moved = $stream && $target->writeStream($destination, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            if ($moved) {
                rescue(fn () => $source->delete($path));
            } else {
                throw MediaFileStorageException::moveFailed($path, $disk, $destination, $destinationDisk);
            }
        }

    }
}
