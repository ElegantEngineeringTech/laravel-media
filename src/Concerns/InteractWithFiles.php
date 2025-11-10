<?php

declare(strict_types=1);

namespace Elegantly\Media\Concerns;

use Carbon\CarbonInterval;
use Closure;
use DateTimeInterface;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Helpers\File;
use Elegantly\Media\TemporaryDirectory;
use Elegantly\Media\UrlFormatters\AbstractUrlFormatter;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\File as HttpFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * @property ?MediaType $type
 * @property ?string $disk
 * @property ?string $path
 * @property ?string $extension
 * @property ?string $name
 * @property ?string $file_name
 * @property ?string $mime_type
 * @property ?int $size in bytes
 * @property ?int $height
 * @property ?int $width
 * @property ?float $aspect_ratio
 * @property ?float $duration in Ms
 *
 * @mixin Model
 */
trait InteractWithFiles
{
    /**
     * @return class-string<AbstractUrlFormatter>
     */
    public function getDefaultUrlFormatter(): string
    {
        /** @var class-string<AbstractUrlFormatter> */
        $formatter = config()->string('media.default_url_formatter');

        return $formatter;
    }

    public function dirname(): ?string
    {

        if ($this->path) {
            return dirname($this->path);
        }

        return null;
    }

    public function getDisk(): ?Filesystem
    {
        if (! $this->disk) {
            return null;
        }

        return Storage::disk($this->disk);
    }

    /**
     * @param  null|array<array-key, mixed>  $parameters
     * @param  null|class-string<AbstractUrlFormatter>  $formatter
     */
    public function getUrl(
        ?array $parameters = null,
        ?string $formatter = null
    ): ?string {
        if (! $this->path) {
            return null;
        }

        $url = $this->getDisk()?->url($this->path);

        if ($url) {
            $formatter ??= $this->getDefaultUrlFormatter();

            return (new $formatter)->format($url, $parameters);
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $options
     * @param  null|array<array-key, mixed>  $parameters
     * @param  null|class-string<AbstractUrlFormatter>  $formatter
     */
    public function getTemporaryUrl(
        DateTimeInterface $expiration,
        array $options = [],
        ?array $parameters = null,
        ?string $formatter = null,
    ): ?string {
        if (! $this->path) {
            return null;
        }

        $url = $this->getDisk()?->temporaryUrl($this->path, $expiration, $options);

        if ($url) {
            $formatter ??= $this->getDefaultUrlFormatter();

            return (new $formatter)->format($url, $parameters);
        }

        return null;
    }

    /**
     * @return null|resource
     */
    public function readStream()
    {
        if (! $this->path) {
            return null;
        }

        return $this->getDisk()?->readStream($this->path);
    }

    public function deleteFile(): bool
    {
        if (! $this->path) {
            return true;
        }

        $filesystem = $this->getDisk();

        if ($filesystem?->exists($this->path)) {
            return (bool) $filesystem->delete($this->path);
        }

        return true;
    }

    public function deleteDirectory(): bool
    {
        $dirname = $this->dirname();

        if (! $dirname) {
            return true;
        }

        $filesystem = $this->getDisk();

        if (! $filesystem) {
            return true;
        }

        return $filesystem->deleteDirectory($dirname);
    }

    /**
     * @return ?string The new file path on success, null on failure
     */
    public function putFile(
        string $disk,
        string $destination,
        UploadedFile|HttpFile $file,
        string $name,
    ): string|null|false {
        $this->disk = $disk;

        $destination = Str::rtrim($destination, '/');
        $extension = File::extension($file);

        $name = File::sanitizeFilename($name);

        $pathname = $file->getPathname();

        $fileName = $extension ? "{$name}.{$extension}" : $name;

        $path = $this->getDisk()?->putFileAs(
            $destination,
            $file,
            $fileName,
        ) ?: null;

        $this->path = $path;
        $this->name = $name;
        $this->extension = $extension;
        $this->file_name = $fileName;

        $this->mime_type = File::mimeType($file);
        $this->size = $file->getSize();

        try {
            if ($dimension = File::dimension($pathname)) {
                $this->height = (int) $dimension->height;
                $this->width = (int) $dimension->width;
                $this->aspect_ratio = $dimension->getAspectRatio()->value;
            }

            $this->duration = File::duration($pathname);
        } catch (\Throwable $th) {
            report($th);
        }

        try {
            $this->type = File::type($pathname);
        } catch (\Throwable $th) {
            $this->type = MediaType::Other;
        }

        return $path;
    }

    /**
     * @return ?string The new file path on success, null on failure
     */
    public function copyFileTo(
        string|Filesystem $disk,
        string $path,
    ): ?string {
        $filesystem = $disk instanceof Filesystem ? $disk : Storage::disk($disk);

        $stream = $this->readStream();

        if (! $stream) {
            return null;
        }

        $result = $filesystem->writeStream(
            $path,
            $stream
        );

        return $result ? $path : null;
    }

    /**
     * @return ?string The new file path on success, null on failure
     */
    public function moveFileTo(
        string $disk,
        string $path,
    ): ?string {

        if ($disk === $this->disk && $path === $this->path) {
            return $path;
        }

        if ($this->copyFileTo($disk, $path)) {
            try {
                $this->deleteFile();
            } catch (\Throwable $th) {
                report($th);
            }

            $this->disk = $disk;
            $this->path = $path;
            $this->save();

            return $path;
        }

        return null;
    }

    /**
     * Transform the media file inside a temporary directory while keeping the same Model
     * Usefull to optimize or convert the media file afterwards
     *
     * @param  Closure(HttpFile $copy, \Spatie\TemporaryDirectory\TemporaryDirectory $temporaryDirectory): HttpFile  $transform
     * @return $this
     */
    public function transformFile(Closure $transform): static
    {

        TemporaryDirectory::callback(function ($temporaryDirectory) use ($transform) {
            /** Used to delete the old file at the end */
            $clone = clone $this;

            $disk = $this->disk;
            $path = $this->path;
            $name = $this->name;

            if (! $path || ! $disk || ! $name) {
                return $this;
            }

            $storage = TemporaryDirectory::storage($temporaryDirectory);

            $copy = $this->copyFileTo(
                disk: $storage,
                path: $path
            );

            if (! $copy) {
                return;
            }

            $file = $transform(
                new HttpFile($storage->path($copy)),
                $temporaryDirectory
            );

            $result = $this->putFile(
                disk: $disk,
                destination: dirname($path),
                file: $file,
                name: $name
            );

            if (
                $result &&
                $clone->path !== $this->path
            ) {
                $clone->deleteFile();
            }

            $this->save();

        });

        return $this;
    }

    public function humanReadableSize(
        int $precision = 0,
        ?int $maxPrecision = null
    ): ?string {
        if (! $this->size) {
            return null;
        }

        return Number::fileSize($this->size, $precision, $maxPrecision);
    }

    public function humanReadableDuration(
        ?int $syntax = null,
        bool $short = false,
        int $parts = CarbonInterval::NO_LIMIT,
        ?int $options = null
    ): ?string {
        if (! $this->duration) {
            return null;
        }

        return CarbonInterval::milliseconds($this->duration)
            ->cascade()
            ->forHumans($syntax, $short, $parts, $options);
    }

    /**
     * Whether the file is safe to use with <img>, <video>, etc. in the browser.
     */
    public function isBrowserSafe(): bool
    {
        return match ($this->type) {
            MediaType::Image => in_array($this->mime_type, ['image/apng', 'image/avif', 'image/gif', 'image/jpeg', 'image/png', 'image/svg', 'image/webp']),
            MediaType::Video => in_array($this->mime_type, ['video/mp4', 'video/x-m4v', 'video/webm']),
            MediaType::Audio => in_array($this->mime_type, ['audio/mpeg', 'audio/mp4', 'audio/webm', 'audio/wav']),
            MediaType::Pdf => true,
            default => false,
        };
    }
}
