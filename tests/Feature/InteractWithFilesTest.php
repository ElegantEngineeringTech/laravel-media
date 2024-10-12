<?php

use Elegantly\Media\Database\Factories\MediaFactory;
use Elegantly\Media\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('copies a file to a disk and path', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media');

    $file = UploadedFile::fake()->image('foo.jpg', width: 16, height: 9);

    $media->storeFile(
        file: $file,
        disk: 'media'
    );

    Storage::disk('media')->assertExists($media->path);

    Storage::fake('media-copy');

    $copy = $media->copyFileTo(
        disk: 'media-copy',
        path: $media->path
    );

    Storage::disk('media-copy')->assertExists($copy);
});
