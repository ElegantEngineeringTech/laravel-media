<?php

use Elegantly\Media\FileDownloaders\FileDownloader;
use Spatie\TemporaryDirectory\TemporaryDirectory;

it('download a file from an url as a temporary file', function () {

    $temporaryDirectory = (new TemporaryDirectory())
        ->location(storage_path('media-tmp'))
        ->create();

    $path = FileDownloader::getTemporaryFile($this->dummy_pdf_url, $temporaryDirectory);

    expect(is_file($path))->toBe(true);

    $temporaryDirectory->delete();

    expect(is_file($path))->toBe(false);
});
