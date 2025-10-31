<?php

declare(strict_types=1);

use Elegantly\Media\FileDownloaders\HttpFileDownloader;
use Spatie\TemporaryDirectory\TemporaryDirectory;

it('download a file from an url as a temporary file', function () {

    $temporaryDirectory = (new TemporaryDirectory)
        ->location(storage_path('media-tmp'))
        ->create();

    $path = HttpFileDownloader::fromUrl(
        $this->dummy_pdf_url,
        $temporaryDirectory->path()
    );

    expect(is_file($path))->toBe(true);

    $temporaryDirectory->delete();

    expect(is_file($path))->toBe(false);
});

it('download a file from an url as a temporary file and sets the right extension', function () {

    $temporaryDirectory = (new TemporaryDirectory)
        ->location(storage_path('media-tmp'))
        ->create();

    $path = HttpFileDownloader::fromUrl(
        'https://icon.horse/icon/discord.com',
        $temporaryDirectory->path()
    );

    expect(is_file($path))->toBe(true);
    expect(str($path)->endsWith('.png'))->toBe(true);

    $temporaryDirectory->delete();

    expect(is_file($path))->toBe(false);
})->skip();
