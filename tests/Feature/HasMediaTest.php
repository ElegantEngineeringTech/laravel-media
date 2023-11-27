<?php

use Finller\LaravelMedia\Tests\Models\Test;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('create a media and store the files', function () {
    Storage::fake('media');

    $model = new Test();
    $model->save();

    $file = UploadedFile::fake()->image('foo.jpg', width: 16, height: 9);

    $model->saveMedia(
        file: $file,
        collection_name: 'avatar',
        disk: 'media'
    );

    $media = $model->getMedia('avatar')->first();

    Storage::disk('media')->assertExists($media->path);
})->only();
