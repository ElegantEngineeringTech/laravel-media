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
use League\Flysystem\CorruptedPathDetected;
use League\Flysystem\PathTraversalDetected;
use League\Flysystem\WhitespacePathNormalizer;
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
     * The MIME type guessed from the content of the file, never from what the client sent.
     * Null when the file is missing or can not be read.
     *
     * Depending on the operating system, the guessed MIME type of m3u8 playlists
     * is unreliable (audio/x-mpegurl, text/plain...), so they are read instead.
     */
    public static function mimeType(string|HttpFile|UploadedFile $file): ?string
    {
        $file = static::asFile($file);

        if (! static::isReadable($file)) {
            return null;
        }

        $extension = static::extension($file);

        return match ($extension) {
            'm3u8' => static::playlistMimeType($file->getPathname()) ?? $file->getMimeType(),
            default => $file->getMimeType(),
        };
    }

    /**
     * The MIME type of an extended M3U playlist, which always starts with "#EXTM3U":
     * HLS when it has "#EXT-X-" tags, a plain playlist otherwise.
     * Null when the file is not an extended M3U playlist.
     */
    protected static function playlistMimeType(string $path): ?string
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return null;
        }

        try {
            if (! str_starts_with((string) fgets($handle, 16), '#EXTM3U')) {
                return null;
            }

            // Lines are read in chunks so a crafted file without line breaks is never loaded at once
            while (($line = fgets($handle, 4096)) !== false) {
                if (str_starts_with($line, '#EXT-X-')) {
                    return 'application/vnd.apple.mpegurl';
                }
            }

            return 'audio/x-mpegurl';
        } finally {
            fclose($handle);
        }
    }

    /**
     * The extension is lowercased and only kept when it is strictly alphanumeric
     * (1 to 10 chars). Otherwise it is guessed from the file content, so a crafted
     * name can never inject unexpected characters into a stored path.
     *
     * Null when it can not be guessed, e.g. when the file is missing or can not be read.
     */
    public static function extension(string|HttpFile|UploadedFile $file): ?string
    {
        $file = static::asFile($file);

        $extension = static::sanitizeExtension(
            $file instanceof UploadedFile ? $file->getClientOriginalExtension() : $file->getExtension()
        );

        if ($extension !== null) {
            return $extension;
        }

        if (! static::isReadable($file)) {
            return null;
        }

        $mimeType = $file->getMimeType();

        return $mimeType ? static::extensionFromMimeType($mimeType) : null;
    }

    /**
     * Whether the file exists and can be read.
     *
     * The path is taken from getPathname() rather than from the SplFileInfo checks (isFile(), isReadable()),
     * which look at the path given to the constructor. Some uploads, like the temporary ones of Livewire,
     * are built on a placeholder path and override getPathname() to point at the real file.
     */
    protected static function isReadable(HttpFile|UploadedFile $file): bool
    {
        $pathname = $file->getPathname();

        return is_file($pathname) && is_readable($pathname);
    }

    /**
     * Sanitizes an extension, with or without its leading dot, e.g. ".MP4" becomes "mp4".
     *
     * Null when it is not strictly alphanumeric (1 to 10 chars), so a crafted extension
     * can never inject unexpected characters into a stored path.
     */
    public static function sanitizeExtension(string $extension): ?string
    {
        $extension = mb_strtolower(mb_ltrim(mb_trim($extension), '.'));

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
     * Non-Latin scripts are transliterated, e.g. "報告書 2024" becomes "bao_gao_shu_2024",
     * and dots, slashes and plus signs separate words, e.g. "v1.2.3" becomes "v1_2_3".
     *
     * Null when nothing is left of the name once sanitized, e.g. "🎉" or "!!!".
     */
    public static function sanitizeName(string $name): ?string
    {
        $name = Str::slug(
            Str::transliterate($name),
            separator: '_',
            dictionary: ['@' => 'at', '&' => 'and', '+' => '', '.' => '', '/' => '', '\\' => '']
        );

        return $name === '' ? null : $name;
    }

    /**
     * Sanitizes a file name, both its name and its extension, e.g. "My Video.MP4" becomes "my_video.mp4".
     *
     * An extension that is not valid is sanitized as a part of the name, e.g. "foo.b@r" becomes "foo_b_at_r".
     *
     * Null when nothing is left of the name once sanitized, e.g. "🎉.png" or ".mp4".
     *
     * @see File::sanitizeName()
     * @see File::sanitizeExtension()
     */
    public static function sanitizeFilename(string $filename): ?string
    {
        $filename = basename(mb_trim($filename));

        $extension = static::sanitizeExtension(pathinfo($filename, PATHINFO_EXTENSION));

        $name = static::sanitizeName(
            $extension === null ? $filename : pathinfo($filename, PATHINFO_FILENAME)
        );

        return $name === null ? null : static::withExtension($name, $extension);
    }

    /**
     * The path as the storage writes it, e.g. "/uploads//a/./b/movie.mp4" becomes "uploads/a/b/movie.mp4".
     *
     * Every path given to a disk is normalized this way before it is used, so a path saved as it was given
     * could differ from the one of the file, and break what is built from it like the urls.
     *
     * @throws PathTraversalDetected When the path leaves the root of the disk, e.g. "../movie.mp4"
     * @throws CorruptedPathDetected When the path contains control characters
     */
    public static function normalizePath(string $path): string
    {
        return (new WhitespacePathNormalizer)->normalizePath($path);
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
        string $destinationPath,
    ): void {
        $source = Storage::disk($disk);
        $target = Storage::disk($destinationDisk);

        if ($target->exists($destinationPath)) {
            throw MediaFileStorageException::moveTargetExists($path, $disk, $destinationPath, $destinationDisk);
        }

        if ($disk === $destinationDisk) {

            if (! $source->move($path, $destinationPath)) {
                throw MediaFileStorageException::moveFailed($path, $disk, $destinationPath, $destinationDisk);
            }

        } else {
            $stream = $source->readStream($path);

            if (! $stream) {
                throw MediaFileStorageException::moveFailed($path, $disk, $destinationPath, $destinationDisk);
            }

            $moved = false;

            try {
                $moved = $target->writeStream($destinationPath, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }

                if (! $moved) {
                    rescue(fn () => $target->delete($destinationPath));
                }
            }

            if (! $moved) {
                throw MediaFileStorageException::moveFailed($path, $disk, $destinationPath, $destinationDisk);
            }

            rescue(fn () => $source->delete($path));
        }

    }
}
