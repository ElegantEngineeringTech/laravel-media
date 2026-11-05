<?php

declare(strict_types=1);

namespace Elegantly\Media;

use Closure;
use Elegantly\Media\Exceptions\MediaStreamNotReadableException;
use Elegantly\Media\Models\Media;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;

/**
 * Zip media using the native `zip` binary instead of PHP.
 * Files are placed in a folder at the root of the archive.
 *
 * @template TMedia of Media
 */
class NativeMediaZipper implements Responsable
{
    /**
     * @param  Collection<int, TMedia>  $media
     * @param  null|string  $folder  Folder inside the archive. Defaults to the file name without extension, use '' to put files at the root.
     * @param  int  $compressionLevel  0 (store only) to 9. Media files are usually already compressed.
     * @param  null|string  $binary  Defaults to media.zip.zip_binaries.
     */
    public function __construct(
        public Collection $media = new Collection,
        public string $fileName = 'media.zip',
        public ?string $folder = null,
        public int $compressionLevel = 0,
        public ?float $timeout = null,
        public ?string $binary = null,
    ) {
        //
    }

    public function getFolder(): string
    {
        return trim($this->folder ?? pathinfo($this->fileName, PATHINFO_FILENAME), '/');
    }

    /**
     * Build the archive in a temporary directory and pass its path to the callback.
     * The archive is deleted once the callback returns.
     *
     * @template TValue
     *
     * @param  Closure(string $path):TValue  $callback
     * @return TValue
     */
    public function zip(Closure $callback): mixed
    {
        return TemporaryDirectory::callback(function (TemporaryDirectory $temporaryDirectory) use ($callback) {
            return $callback($this->build($temporaryDirectory));
        });
    }

    /**
     * @param  array<array-key, mixed>  $options  writeStream options
     */
    public function toFile(Filesystem $storage, string $path, array $options = []): string|false
    {
        return $this->zip(function (string $archive) use ($storage, $path, $options) {
            $stream = fopen($archive, 'r');

            if ($stream === false) {
                return false;
            }

            try {
                $success = $storage->writeStream($path, $stream, $options);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            return $success ? $path : false;
        });
    }

    public function toResponse($request): StreamedResponse
    {
        $temporaryDirectory = TemporaryDirectory::make();

        try {
            $archive = $this->build($temporaryDirectory);
        } catch (\Throwable $th) {
            $temporaryDirectory->delete();
            throw $th;
        }

        return new StreamedResponse(function () use ($archive, $temporaryDirectory) {
            try {
                readfile($archive);
            } finally {
                $temporaryDirectory->delete();
            }
        }, 200, [
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $this->fileName,
                str_replace('%', '', Str::ascii($this->fileName)),
            ),
            'Content-Type' => 'application/zip',
            'Content-Length' => (string) filesize($archive),
        ]);
    }

    /**
     * @return string The archive path
     */
    protected function build(TemporaryDirectory $temporaryDirectory): string
    {
        $content = $temporaryDirectory->path('content');
        $archive = $temporaryDirectory->path('archive.zip');
        $folder = $this->getFolder();
        $destination = $folder === '' ? $content : "{$content}/{$folder}";

        if (! is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        foreach ($this->media->values() as $index => $item) {
            $this->place($item, "{$destination}/{$index}_".basename($item->file_name ?? (string) $item->id));
        }

        $process = new Process(
            command: [$this->binary ?? MediaServiceProvider::getZipBinaries(), '-r', '-q', '-X', '-D', "-{$this->compressionLevel}", $archive, '.'],
            cwd: $content,
            timeout: $this->timeout,
        );

        $process->mustRun();

        return $archive;
    }

    /**
     * Symlink files from local disks (zip follows them), copy the others.
     */
    protected function place(Media $media, string $target): void
    {
        $disk = $media->getDisk();

        if (
            $media->path &&
            $disk instanceof FilesystemAdapter &&
            $disk->getAdapter() instanceof LocalFilesystemAdapter &&
            @symlink($disk->path($media->path), $target)
        ) {
            return;
        }

        $stream = $media->readStream();

        if ($stream === null) {
            throw MediaStreamNotReadableException::forMedia($media);
        }

        try {
            if (file_put_contents($target, $stream) === false) {
                throw MediaStreamNotReadableException::forMedia($media);
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
