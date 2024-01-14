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
    /**
     * @param  Collection<int, Media>  $media
     */
    public function __construct(
        public Collection $media = new Collection(),
        public string $fileName = 'media.zip',
        public array $zipStreamOptions = [],
    ) {
        $this->zipStreamOptions['outputName'] = $fileName;
    }

    public function toFile(Filesystem $storage, string $path, array $options = []): string|false
    {
        $tempStream = fopen('php://memory', 'w+');

        $zipStream = $this->getZipStream([
            'outputStream' => $tempStream,
        ]);

        $success = $storage->writeStream($path, $tempStream, $options);

        fclose($tempStream);

        return $success ? $path : false;
    }

    public function getZipStream(array $options = [])
    {
        $zip = new ZipStream(
            ...$this->zipStreamOptions,
            ...$options,
        );

        foreach ($this->media as $item) {
            $stream = $item->readStream();

            $zip->addFileFromStream(
                fileName: $item->file_name,
                stream: $stream
            );

            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $zip->finish();

        return $zip;
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request): StreamedResponse
    {
        return new StreamedResponse(fn () => $this->getZipStream(), 200, [
            'Content-Disposition' => "attachment; filename=\"{$this->fileName}\"",
            'Content-Type' => 'application/octet-stream',
        ]);
    }
}
