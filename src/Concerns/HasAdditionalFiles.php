<?php

declare(strict_types=1);

namespace Elegantly\Media\Concerns;

use Elegantly\Media\Exceptions\MediaFileStorageException;
use Elegantly\Media\FileDownloaders\HttpFileDownloader;
use Elegantly\Media\Helpers\File;
use Elegantly\Media\MediaServiceProvider;
use Elegantly\Media\TemporaryDirectory;
use Elegantly\Media\ValueObjects\AdditionalFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\File as HttpFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @mixin Model
 *
 * @property ?Collection<int, AdditionalFile> $additional_files
 */
trait HasAdditionalFiles
{
    protected function putAdditionalFile(
        string $disk,
        string $destination,
        UploadedFile|HttpFile $file,
        string $name,
    ): AdditionalFile {

        $destination = Str::rtrim($destination, '/');
        $extension = File::extension($file);
        $name = File::sanitizeFilename($name);
        $fileName = $extension ? "{$name}.{$extension}" : $name;
        $mimeType = File::mimeType($file);
        $size = $file->getSize();

        $path = Storage::disk($disk)->putFileAs($destination, $file, $fileName);

        if (! $path) {
            throw MediaFileStorageException::storeFailed($file->getPath(), $disk, $destination);
        }

        return new AdditionalFile([
            'disk' => $disk,
            'name' => $name,
            'extension' => $extension,
            'file_name' => $fileName,
            'path' => $path,
            'size' => $size,
            'mime_type' => $mimeType,
        ]);
    }

    protected function storeAdditionalFileFromHttpFile(
        UploadedFile|HttpFile $file,
        ?string $destination = null,
        ?string $name = null,
        ?string $disk = null,
    ): static {
        $pathGenerator = MediaServiceProvider::getDefaultPathGenerator();

        $destination ??= (new $pathGenerator)->source($this)->value();
        $name ??= File::name($file) ?? Str::random(6);
        $disk ??= $this->disk ?? config()->string('media.disk', config()->string('filesystems.default', 'local'));

        $additionalFile = $this->putAdditionalFile(
            disk: $disk,
            destination: $destination,
            file: $file,
            name: $name,
        );

        $this->additional_files = collect($this->additional_files)->add($additionalFile);

        return $this;
    }

    /**
     * @param  string|UploadedFile|HttpFile|resource  $file
     */
    public function storeAdditionalFile(
        mixed $file,
        ?string $destination = null,
        ?string $name = null,
        ?string $disk = null,
    ): static {
        if ($file instanceof UploadedFile || $file instanceof HttpFile) {
            return $this->storeAdditionalFileFromHttpFile($file, $destination, $name, $disk);
        }

        if (! is_string($file) || filter_var($file, FILTER_VALIDATE_URL)) {
            return TemporaryDirectory::callback(function ($temporaryDirectory) use ($file, $destination, $name, $disk) {
                $path = HttpFileDownloader::download(
                    file: $file,
                    destination: $temporaryDirectory->path()
                );

                return $this->storeAdditionalFileFromHttpFile(new HttpFile($path, false), $destination, $name, $disk);
            });
        }

        return $this->storeAdditionalFileFromHttpFile(new HttpFile($file), $destination, $name, $disk);
    }

    /**
     * Moves every additional file to another disk, keeping their paths.
     *
     * @throws MediaFileStorageException When one of the files can't be moved
     */
    public function moveAdditionalFilesTo(string $disk): static
    {
        if (
            $this->additional_files === null ||
            $this->additional_files->isEmpty()
        ) {
            return $this;
        }

        try {
            foreach ($this->additional_files as $file) {
                $file->moveTo($disk);
            }
        } finally {
            // Persist the files already moved, even when a later one fails
            $this->save();
        }

        return $this;
    }

    public function deleteAdditionalFiles(): bool
    {
        if ($this->additional_files === null) {
            return true;
        }

        foreach ($this->additional_files as $file) {

            $filesystem = $file->getDisk();

            if (! $filesystem->delete($file->path)) {
                return false;
            }
        }

        return true;
    }
}
