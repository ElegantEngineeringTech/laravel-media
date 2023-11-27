<?php

use Finller\LaravelMedia\Tests\Models\Test;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('get the correct media collection', function () {
    $model = new Test();

    expect($model->getMediaCollections()->has('files'))->toBe(true);
});

it('create a media, store files and generate conversions', function () {
    Storage::fake('media');

    $model = new Test();
    $model->save();

    $file = UploadedFile::fake()->image('foo.jpg');

    $media = $model->saveMedia(
        file: $file,
        collection_name: 'files',
        disk: 'media'
    );

    $media->refresh();

    expect($model->getMediaConversions($media)->count())->toBe(1);

    expect($media->collection_name)->toBe('files');

    Storage::disk('media')->assertExists($media->path);

    expect($media->generated_conversions->count())->toBe(1);

    $generatedConversion = $media->getGeneratedConversion('optimized');

    expect($generatedConversion)->not->toBe(null);

    Storage::disk('media')->assertExists($generatedConversion->path);
});
