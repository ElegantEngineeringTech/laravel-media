<?php

declare(strict_types=1);

namespace Elegantly\Media;

use Closure;
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
 * Zip a collection of files using the native `zip` binary instead of PHP.
 * Files are placed in a folder at the root of the archive.
 *
 * Subclasses define how each item is named and how its content is placed in the archive.
 *
 * @template TItem
 */
abstract class AbstractNativeZipper implements Responsable
{
    /**
     * @param  Collection<array-key, TItem>  $items
     * @param  null|string  $folder  Folder inside the archive. Defaults to the file name without extension, use '' to put files at the root.
     * @param  int  $compressionLevel  0 (store only) to 9. Media files are usually already compressed.
     * @param  null|string  $binary  Defaults to media.zip.zip_binaries.
     */
    public function __construct(
        public Collection $items = new Collection,
        public string $fileName = 'archive.zip',
        public ?string $folder = null,
        public int $compressionLevel = 0,
        public ?float $timeout = null,
        public ?string $binary = null,
    ) {
        //
    }

    /**
     * The file name of the item inside the archive folder.
     *
     * @param  TItem  $item
     */
    abstract protected function getItemName(mixed $item, int $index): string;

    /**
     * Write or link the item content at $target.
     * Use placeFromPath, placeFromDisk or placeFromStream.
     *
     * @param  TItem  $item
     */
    abstract protected function placeItem(mixed $item, string $target): void;

    /**
     * Whether the item is put in the archive. Override to skip some of them.
     *
     * @param  TItem  $item
     */
    protected function shouldIncludeItem(mixed $item): bool
    {
        return true;
    }

    public function getFolder(): string
    {
        return mb_trim($this->folder ?? pathinfo($this->fileName, PATHINFO_FILENAME), '/');
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
        $items = $this->items->filter(fn ($item) => $this->shouldIncludeItem($item))->values();

        if ($items->isEmpty()) {
            // zip refuses to create an empty archive: write the end of central directory record alone
            file_put_contents($archive, "PK\x05\x06".str_repeat("\0", 18));

            return $archive;
        }

        if (! is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        foreach ($items as $index => $item) {
            $this->placeItem($item, "{$destination}/".basename($this->getItemName($item, $index)));
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
     * Symlink a local file (zip follows symlinks), copy it if symlinks are not supported.
     *
     * @return bool false if the file doesn't exist or can't be placed
     */
    protected function placeFromPath(string $path, string $target): bool
    {
        return is_file($path) && (@symlink($path, $target) || @copy($path, $target));
    }

    /**
     * Symlink files from local disks, stream the others.
     *
     * @return bool false if the file can't be read or placed
     */
    protected function placeFromDisk(Filesystem $disk, string $path, string $target): bool
    {
        if (
            $disk instanceof FilesystemAdapter &&
            $disk->getAdapter() instanceof LocalFilesystemAdapter
        ) {
            return $this->placeFromPath($disk->path($path), $target);
        }

        return $this->placeFromStream($disk->readStream($path), $target);
    }

    /**
     * Copy the stream to $target and close it.
     *
     * @param  null|resource  $stream
     * @return bool false if the stream is not readable or can't be written
     */
    protected function placeFromStream($stream, string $target): bool
    {
        if (! is_resource($stream)) {
            return false;
        }

        try {
            return file_put_contents($target, $stream) !== false;
        } finally {
            fclose($stream);
        }
    }
}
