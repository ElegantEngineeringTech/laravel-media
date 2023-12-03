<?php

namespace Finller\Media\FileDownloaders;

use Exception;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class FileDownloader
{
    public static function getTemporaryFile(string $url, TemporaryDirectory $temporaryDirectory = null): string
    {
        $context = stream_context_create([
            'http' => [
                'header' => 'User-Agent: Finller laravel-media package',
            ],
        ]);

        if (! $stream = @fopen($url, 'r', false, $context)) {
            throw new Exception("Can't reach the url: {$url}");
        }

        $temporaryDirectory ??= (new TemporaryDirectory())
            ->location(storage_path('media-tmp'))
            ->deleteWhenDestroyed()
            ->create();

        $path = tempnam($temporaryDirectory->path(), 'media-');

        file_put_contents($path, $stream);

        fclose($stream);

        return $path;
    }
}
