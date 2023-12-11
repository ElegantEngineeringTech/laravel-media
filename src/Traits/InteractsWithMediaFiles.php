<?php

namespace Finller\Media\Traits;

use Exception;
use Finller\Media\FileDownloaders\FileDownloader;
use Finller\Media\Helpers\File;
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
 * @property ?int $size
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

    /**
     * @return null|resource
     */
    public function readStream()
    {
        return $this->getDisk()?->readStream($this->path);
    }

    /**
     * @param  string  $path including the file name
     */
    public function copyFileTo(string $path): ?string
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

        $this->copyFileTo($path);

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
    ): string|false {
        if (! $this->path) {
            throw new Exception('['.static::class.']'."Can't put a file to the instance because the main path is not defined");
        }

        if (is_string($file) && filter_var($file, FILTER_VALIDATE_URL)) {
            $file = new HttpFile(FileDownloader::getTemporaryFile($file));
        } elseif (is_string($file)) {
            $file = new HttpFile($file);
        }

        $fileName ??= File::extractFilename($file, $name);

        $path = $this->getDisk()->putFileAs(
            $this->getDirname(),
            $file,
            $fileName
        );

        if (! $path) {
            throw new Exception('['.static::class.']'."Putting the file {$fileName} to the instance failed");
        }

        return $path;
    }

    public function deleteDirectory(): bool
    {
        if (! $this->path) {
            return true;
        }

        return $this
            ->getDisk()
            ->deleteDirectory($this->getDirname());
    }

    public function humanReadableSize(int $precision = 0, ?int $maxPrecision = null): ?string
    {
        if (! $this->size) {
            return null;
        }

        return Number::fileSize($this->size, $precision, $maxPrecision);
    }
}
