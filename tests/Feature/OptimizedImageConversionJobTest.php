<?php

use Finller\LaravelMedia\Tests\Models\TestWithMultipleConversions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('generate a webp file conversion', function () {
    Storage::fake('media');

    $model = new TestWithMultipleConversions();
    $model->save();

    $file = UploadedFile::fake()->image('foo.jpg');
    $model->addMedia(
        file: $file,
        collection_name: 'files',
        disk: 'media'
    );

    $media = $model->getMedia('files')->first();

    $generatedConversion = $media->getGeneratedConversion('webp');

    expect($generatedConversion)->not->toBe(null);
    expect($generatedConversion->extension)->toBe('webp');

    Storage::disk('media')->assertExists($generatedConversion->path);
});
