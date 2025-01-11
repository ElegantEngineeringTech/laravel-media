<?php

declare(strict_types=1);

use Elegantly\Media\Database\Factories\MediaFactory;
use Elegantly\Media\Helpers\File;
use Elegantly\Media\Models\Media;
use Illuminate\Http\File as HttpFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Image;

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

it('transforms a file and delete the original one', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media');

    $file = UploadedFile::fake()->image('foo.jpg', width: 16, height: 9);

    $media->storeFile(
        file: $file,
        disk: 'media'
    );

    $path = $media->path;

    Storage::disk('media')->assertExists($path);

    $media->transformFile(function ($file) {

        $path = $file->getRealPath();
        $basename = dirname($path);
        $name = File::name($path);

        $new = "{$basename}/{$name}.png";

        Image::load($path)
            ->save($new);

        return new HttpFile($new);
    });

    Storage::disk('media')->assertMissing($path);

    Storage::disk('media')->assertExists($media->path);

    $path = $media->path;

    $media->transformFile(function ($file) {
        $path = $file->getRealPath();

        Image::load($path)
            ->optimize()
            ->save($path);

        return new HttpFile($path);
    });

    Storage::disk('media')->assertExists($path);

});
