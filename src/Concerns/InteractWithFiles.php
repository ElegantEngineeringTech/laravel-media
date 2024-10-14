<?php

namespace Elegantly\Media\Concerns;

use Carbon\CarbonInterval;
use DateTimeInterface;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Helpers\File;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\File as HttpFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * @property ?string $disk
 * @property ?string $path
 * @property ?string $extension
 * @property ?string $name
 * @property ?string $file_name
 * @property ?string $mime_type
 * @property ?int $size
 * @property ?int $height
 * @property ?int $width
 * @property ?float $aspect_ratio
 * @property ?float $duration
 * @property ?MediaType $type
 */
trait InteractWithFiles
{
    public function getDisk(): ?Filesystem
    {
        if (! $this->disk) {
            return null;
        }

        return Storage::disk($this->disk);
    }

    public function getUrl(): ?string
    {
        if (! $this->path) {
            return null;
        }

        return $this->getDisk()?->url($this->path);
    }

    /**
     * @param  array<array-key, mixed>  $options
     */
    public function getTemporaryUrl(
        DateTimeInterface $expiration,
        array $options = []
    ): ?string {
        if (! $this->path) {
            return null;
        }

        return $this->getDisk()?->temporaryUrl($this->path, $expiration, $options);
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

        $dimension = File::dimension($file->getPathname());

        $this->height = $dimension?->getHeight();
        $this->width = $dimension?->getWidth();
        $this->aspect_ratio = $dimension?->getRatio(forceStandards: false)->getValue();
        $this->duration = File::duration($file->getPathname());
        $this->mime_type = File::mimeType($file);
        $this->size = $file->getSize();
        $this->type = File::type($file->getPathname());

        return $path;
    }

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

        return CarbonInterval::milliseconds($this->duration)->forHumans($syntax, $short, $parts, $options);
    }
}
