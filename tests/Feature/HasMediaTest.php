<?php

use Finller\LaravelMedia\Tests\Models\Test;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('create a media, store files and generate conversions', function () {
    Storage::fake('media');

    $model = new Test();
    $model->save();

    $file = UploadedFile::fake()->image('foo.jpg', width: 16, height: 9);

    $model->saveMedia(
        file: $file,
        collection_name: 'files',
        disk: 'media'
    );

    $media = $model->getMedia('files')->first();

    Storage::disk('media')->assertExists($media->path);

    expect($media->generated_conversions->count())->toBe(1);

    $generatedConversion = $media->getGeneratedConversion('optimized');

    expect($generatedConversion)->not->toBe(null);

    Storage::disk('media')->assertExists($generatedConversion->path);
});
