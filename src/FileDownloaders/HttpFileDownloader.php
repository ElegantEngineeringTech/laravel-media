<?php

declare(strict_types=1);

namespace Elegantly\Media\FileDownloaders;

use Elegantly\Media\Exceptions\InvalidDataUrlException;
use Elegantly\Media\Helpers\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class HttpFileDownloader
{
    public static function fromUrl(
        string $url,
        string $destination,
    ): string {

        $path = tempnam($destination, 'media-');

        Http::head($url)->throwUnlessStatus(200);

        Http::sink($path)
            ->withUserAgent('Elegantly laravel-media package')
            ->timeout(60 * 10)
            ->get($url);

        return static::renameWithExtension($path);
    }

    /**
     * Whether the value is a data URL (RFC 2397), e.g. "data:image/png;base64,iVBORw0KGgo..."
     */
    public static function isDataUrl(string $value): bool
    {
        return strncasecmp($value, 'data:', 5) === 0;
    }

    /**
     * Writes the content of a data URL (RFC 2397) to a temporary file.
     * The declared media type is ignored: the extension is guessed from the content,
     * so it can't be spoofed.
     *
     * @throws InvalidDataUrlException
     */
    public static function fromDataUrl(
        string $url,
        string $destination,
    ): string {
        $comma = mb_strpos($url, ',');

        if ($comma === false) {
            throw InvalidDataUrlException::missingComma();
        }

        $header = mb_substr($url, 0, $comma);
        $data = mb_substr($url, $comma + 1);

        if (str_ends_with(mb_strtolower($header), ';base64')) {
            $data = base64_decode($data, true);

            if ($data === false) {
                throw InvalidDataUrlException::invalidBase64();
            }
        } else {
            $data = rawurldecode($data);
        }

        $path = tempnam($destination, 'media-');

        if (file_put_contents($path, $data) === false) {
            throw new RuntimeException("Can't write the data URL content to {$path}.");
        }

        return static::renameWithExtension($path);
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
     * @param  resource|string  $file  A resource, an url or a data url
     */
    public static function download(
        $file,
        string $destination
    ): string {
        if (is_string($file)) {

            if (static::isDataUrl($file)) {
                return static::fromDataUrl(
                    url: $file,
                    destination: $destination
                );
            }

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

    /**
     * Renames the file with the extension guessed from its content.
     */
    protected static function renameWithExtension(string $path): string
    {
        if ($extension = File::extension($path)) {
            $pathWithExtension = File::withExtension($path, $extension);

            rename($path, $pathWithExtension);

            return $pathWithExtension;
        }

        return $path;
    }
}
