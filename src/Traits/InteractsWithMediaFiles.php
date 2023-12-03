<?php

namespace Finller\Media\Traits;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\File as SupportFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Illuminate\Http\File as HttpFile;
use Finller\Media\Helpers\File;

/**
 * @property ?string $disk
 * @property ?string $path
 * @property ?int $size
 */
trait InteractsWithMediaFiles
{
    public function getDisk(): ?Filesystem
    {
        if (!$this->disk) {
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
        if (!$this->path) {
            return null;
        }

        file_put_contents($path, $this->readStream());

        return $path;
    }

    public function makeTemporaryFileCopy(TemporaryDirectory $temporaryDirectory = null): string|false
    {
        $temporaryDirectory ??= (new TemporaryDirectory())
            ->location(storage_path('media-tmp'))
            ->deleteWhenDestroyed()
            ->create();

        $path = $temporaryDirectory->path($this->file_name);

        $this->copyFileTo($path);

        return $path;
    }

    public function addFile(
        HttpFile|string $file,
        string $name = null,
        string $fileName = null,
    ): string|false {
        $file = $file instanceof HttpFile ? $file : new HttpFile($file);

        $fileName ??= File::extractFilename($file, $name);

        return $this->getDisk()->putFileAs(
            SupportFile::dirname($this->path),
            $file,
            $fileName
        );
    }

    public function deleteDirectory(): bool
    {
        if (!$this->path) {
            return true;
        }

        return $this->getDisk()->deleteDirectory(
            SupportFile::dirname($this->path)
        );
    }

    public function humanReadableSize(int $precision = 0, int $maxPrecision = null): ?string
    {
        if (!$this->size) {
            return null;
        }

        return Number::fileSize($this->size, $precision, $maxPrecision);
    }
}
