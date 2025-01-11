<?php

declare(strict_types=1);

namespace Elegantly\Media;

use Elegantly\Media\Models\Media;
use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\OperationMode;
use ZipStream\ZipStream;

/**
 * @template TMedia of Media
 */
class MediaZipper implements Responsable
{
    /**
     * @param  Collection<int, TMedia>  $media
     * @param  array<array-key, mixed>  $zipStreamOptions
     */
    public function __construct(
        public Collection $media = new Collection,
        public string $fileName = 'media.zip',
        public array $zipStreamOptions = [],
    ) {
        $this->zipStreamOptions['outputName'] = $fileName;
    }

    /**
     * @param  array<array-key, mixed>  $options  writeStream options
     */
    public function toFile(Filesystem $storage, string $path, array $options = []): string|false
    {
        $temporaryStream = fopen('php://memory', 'w+');

        if ($temporaryStream === false) {
            throw new Exception('PHP Stream creation failed.');
        }

        $zip = $this->getZipStream([
            'outputStream' => $temporaryStream,
        ]);

        $zip->finish();

        $success = $storage->writeStream($path, $temporaryStream, $options);

        if (is_resource($temporaryStream)) {
            fclose($temporaryStream);
        }

        return $success ? $path : false;
    }

    /**
     * @param  array<array-key, mixed>  $options  zipStreamOptions options
     */
    public function getZipStream(array $options = []): ZipStream
    {
        // @phpstan-ignore-next-line
        $zip = new ZipStream(...array_merge(
            $this->zipStreamOptions,
            $options
        ));

        foreach ($this->media as $index => $item) {
            $stream = $item->readStream();

            if ($stream === null) {
                throw new Exception("[Media:{$item->id}] Can't read stream at {$item->path} and disk {$item->disk}.");
            }

            $zip->addFileFromStream(
                fileName: "{$index}_{$item->file_name}",
                stream: $stream,
                exactSize: $item->size,
            );

            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $zip;
    }

    public function getSize(): int
    {
        /** @var int $value */
        $value = $this->media->sum('size');

        return (int) $value;
    }

    public function toResponse($request): StreamedResponse
    {
        $simulation = $this->getZipStream([
            'defaultEnableZeroHeader' => true,
            'sendHttpHeaders' => false,
            'contentType' => 'application/octet-stream',
            'operationMode' => OperationMode::SIMULATE_STRICT, // or SIMULATE_LAX
        ]);

        $size = $simulation->finish();

        return new StreamedResponse(function () {
            $zip = $this->getZipStream([
                'defaultEnableZeroHeader' => true,
                'contentType' => 'application/octet-stream',
            ]);

            $zip->finish();
        }, 200, [
            'Content-Disposition' => "attachment; filename=\"{$this->fileName}\"",
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => $size,
        ]);
    }
}
