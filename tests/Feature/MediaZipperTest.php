<?php

declare(strict_types=1);

use Elegantly\Media\Database\Factories\MediaFactory;
use Elegantly\Media\MediaZipper;
use Elegantly\Media\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('zip media and store it in a file', function () {
    $storage = Storage::fake('media');

    $media = collect([
        MediaFactory::new()->make(),
        MediaFactory::new()->make(),
    ])->each(function (Media $media) {
        $media->storeFile(
            file: UploadedFile::fake()->image('foo.jpg'),
            name: 'avatar',
            disk: 'media'
        );
    });

    $zipper = new MediaZipper($media);

    $zipper->toFile($storage, $zipper->fileName);

    Storage::disk('media')->assertExists($zipper->fileName);
});
