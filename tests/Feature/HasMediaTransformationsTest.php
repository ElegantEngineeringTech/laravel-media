<?php

use Elegantly\Media\Tests\Models\TestWithMediaTransformations;
use Illuminate\Support\Facades\Storage;

it('perform media transformations before storing files', function () {
    Storage::fake('media');

    $model = new TestWithMediaTransformations;
    $model->save();

    $file = $this->getTestFile('images/800x900.jpg');

    $media = $model->addMedia(
        file: $file,
        collection_name: 'avatar',
        disk: 'media'
    );

    Storage::disk('media')->assertExists($media->path);

    expect($media->width)->toBe(500);
    expect($media->height)->toBe(500);
});
