<?php

declare(strict_types=1);

namespace Elegantly\Media\FileDownloaders;

use Elegantly\Media\Helpers\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class FileDownloader
{
    public static function fromUrl(
        string $url,
        string $destination,
    ): string {

        $path = tempnam($destination, 'media-');

        Http::sink($path)
            ->withUserAgent('Elegantly laravel-media package')
            ->timeout(60 * 10)
            ->get($url);

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
