<?php

use ElegantEngineeringTech\Media\Database\Factories\MediaFactory;
use ElegantEngineeringTech\Media\MediaZipper;
use ElegantEngineeringTech\Media\Models\Media;
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
            collection_name: 'avatar',
            name: 'avatar',
            disk: 'media'
        );
    });

    $zipper = new MediaZipper($media);

    $zipper->toFile($storage, $zipper->fileName);

    Storage::disk('media')->assertExists($zipper->fileName);
});
