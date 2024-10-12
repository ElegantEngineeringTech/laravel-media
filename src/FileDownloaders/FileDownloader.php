<?php

namespace Elegantly\Media\FileDownloaders;

use Elegantly\Media\Helpers\File;
use Exception;
use Illuminate\Support\Facades\Storage;

class FileDownloader
{
    public static function fromUrl(
        string $url,
        string $destination,
    ): string {
        $context = stream_context_create([
            'http' => [
                'header' => 'User-Agent: Elegantly laravel-media package',
            ],
        ]);

        if (! $stream = @fopen($url, 'r', false, $context)) {
            throw new Exception("Can't reach the url: {$url}");
        }

        $path = tempnam($destination, 'media-');

        file_put_contents($path, $stream);

        fclose($stream);

        if ($extension = File::extension($path)) {
            $pathWithExtension = "{$path}.{$extension}";

            rename($path, $pathWithExtension);

            return $pathWithExtension;
        }

        return $path;
    }

    /**
     * @param  resource  $resource
     */
    public static function fromResource(
        $resource,
        string $destination,
    ): string {

        $path = tempnam($destination, 'media-');

        $storage = Storage::build([
            'driver' => 'local',
            'root' => $destination,
        ]);

        $storage->writeStream($path, $resource);

        return $path;
    }

    /**
     * @param  resource|string  $file
     */
    public static function download(
        $file,
        string $destination
    ): string {
        if (is_string($file)) {
            return static::fromUrl(
                url: $file,
                destination: $destination
            );
        }

        return static::fromResource(
            resource: $file,
            destination: $destination
        );

    }
}
