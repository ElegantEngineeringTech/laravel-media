<?php

use Elegantly\Media\Database\Factories\MediaFactory;
use Elegantly\Media\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('deletes the files associated with the MediaConversion', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make([
        'disk' => 'media',
    ]);

    Storage::fake('media');

    $media->save();

    $file = UploadedFile::fake()->image('foo.jpg', width: 16, height: 9);

    $conversion = $media->addConversion(
        conversionName: 'poster',
        file: $file,
        name: 'poster',
    );

    Storage::disk('media')->assertExists($conversion->path);

    $conversion->deleteFile();

    Storage::disk('media')->assertMissing($conversion->path);

});

it('On MediaConversion deletion, it deletes the files', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make([
        'disk' => 'media',
    ]);

    Storage::fake('media');

    $media->save();

    $file = UploadedFile::fake()->image('foo.jpg', width: 16, height: 9);

    $conversion = $media->addConversion(
        conversionName: 'poster',
        file: $file,
        name: 'poster',
    );

    Storage::disk('media')->assertExists($conversion->path);

    $conversion->delete();

    Storage::disk('media')->assertMissing($conversion->path);

});
