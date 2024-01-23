<?php

namespace Finller\Media;

use Finller\Media\Models\Media;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\OperationMode;
use ZipStream\ZipStream;

class MediaZipper implements Responsable
{
    public function __construct(
        public Collection $media = new Collection(),
        public string $fileName = 'media.zip',
        public array $zipStreamOptions = [],
    ) {
        $this->zipStreamOptions['outputName'] = $fileName;
    }

    public function toFile(Filesystem $storage, string $path, array $options = []): string|false
    {
        $temporaryStream = fopen('php://memory', 'w+');

        $zip = $this->getZipStream([
            'outputStream' => $temporaryStream,
        ]);

        $zip->finish();

        $success = $storage->writeStream($path, $temporaryStream, $options);

        fclose($temporaryStream);

        return $success ? $path : false;
    }

    public function getZipStream(array $options = [])
    {
        $zip = new ZipStream(...array_merge(
            $this->zipStreamOptions,
            $options
        ));

        /** @var Media $item */
        foreach ($this->media as $index => $item) {
            $stream = $item->readStream();

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
        return (int) $this->media->sum('size');
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
