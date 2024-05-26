<?php

namespace Elegantly\Media\Traits;

use Carbon\CarbonInterval;
use Elegantly\Media\FileDownloaders\FileDownloader;
use Elegantly\Media\Helpers\File;
use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\File as HttpFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File as SupportFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Spatie\TemporaryDirectory\TemporaryDirectory;

/**
 * @property ?string $disk
 * @property ?string $path
 * @property ?int $size The filesize in bytes
 * @property ?float $duration in miliseconds
 */
trait InteractsWithMediaFiles
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

    public function getTemporaryUrl(\DateTimeInterface $expiration, array $options = []): ?string
    {
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
        return $this->getDisk()?->readStream($this->path);
    }

    /**
     * @param  string  $path  including the file name
     */
    public function copyFileLocallyTo(string $path): ?string
    {
        if (! $this->path) {
            return null;
        }

        file_put_contents($path, $this->readStream());

        return $path;
    }

    public function makeTemporaryFileCopy(?TemporaryDirectory $temporaryDirectory = null): string|false
    {
        $temporaryDirectory ??= (new TemporaryDirectory())
            ->location(storage_path('media-tmp'))
            ->deleteWhenDestroyed()
            ->create();

        $path = $temporaryDirectory->path($this->file_name);

        $this->copyFileLocallyTo($path);

        return $path;
    }

    public function getDirname(): ?string
    {
        if (! $this->path) {
            return null;
        }

        return SupportFile::dirname($this->path);
    }

    /**
     * Put a file in the same directory than the main file
     */
    public function putFile(
        string|UploadedFile|HttpFile $file,
        ?string $name = null,
        ?string $fileName = null,
    ): string {

        if (is_string($file) && filter_var($file, FILTER_VALIDATE_URL)) {
            $file = new HttpFile(FileDownloader::getTemporaryFile($file));
        } elseif (is_string($file)) {
            $file = new HttpFile($file);
        }

        $fileName ??= File::extractFilename($file, $name);

        $path = $this->getDisk()?->putFileAs(
            $this->getDirname(),
            $file,
            $fileName
        );

        if (! $path) {
            throw new Exception('['.static::class.']'."Putting the file {$fileName} to the instance failed");
        }

        return $path;
    }

    public function moveFiles(
        ?string $disk = null,
        ?string $path = null
    ): static {
        if (! $disk && ! $path) {
            return $this;
        }

        $newDisk = $disk ?? $this->disk;
        $newPath = $path ?? $this->path;

        $temporaryDirectory = (new TemporaryDirectory())
            ->location(storage_path('media-tmp'))
            ->deleteWhenDestroyed()
            ->create();

        $temporaryFilePath = $this->makeTemporaryFileCopy($temporaryDirectory);

        $filesystem = Storage::disk($newDisk);

        $filesystem->putFileAs(
            path: SupportFile::dirname($newPath),
            file: new HttpFile($temporaryFilePath),
            name: File::extractFilename($newPath)
        );

        $temporaryDirectory->delete();

        $this->deleteFile();

        $this->disk = $newDisk;
        $this->path = $newPath;

        return $this;
    }

    public function deleteDirectory(): bool
    {
        if (! $this->path) {
            return true;
        }

        return $this->getDisk()?->deleteDirectory($this->getDirname());
    }

    public function deleteFile(): bool
    {
        if (! $this->path) {
            return true;
        }

        return $this->getDisk()?->delete($this->path);
    }

    public function humanReadableSize(int $precision = 0, ?int $maxPrecision = null): ?string
    {
        if (! $this->size) {
            return null;
        }

        return Number::fileSize($this->size, $precision, $maxPrecision);
    }

    public function humanReadableDuration(
        ?int $syntax = null,
        ?bool $short = false,
        ?int $parts = -1,
        ?int $options = null
    ): ?string {
        if (! $this->duration) {
            return null;
        }

        return CarbonInterval::milliseconds($this->duration)->forHumans($syntax, $short, $parts, $options);
    }
}
