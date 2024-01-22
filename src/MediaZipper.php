<?php

namespace Finller\Media;

use Finller\Media\Models\Media;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
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

        $this->getZipStream([
            'outputStream' => $temporaryStream,
        ]);

        $success = $storage->writeStream($path, $temporaryStream, $options);

        fclose($temporaryStream);

        return $success ? $path : false;
    }

    public function getZipStream(array $options = [])
    {
        $zip = new ZipStream(
            ...$this->zipStreamOptions,
            ...$options,
        );

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

        $zip->finish();

        return $zip;
    }

    public function toResponse($request): StreamedResponse
    {
        return new StreamedResponse(fn () => $this->getZipStream(), 200, [
            'Content-Disposition' => "attachment; filename=\"{$this->fileName}\"",
            'Content-Type' => 'application/octet-stream',
        ]);
    }
}
