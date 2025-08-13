<?php

declare(strict_types=1);

use Elegantly\Media\Tests\Models\TestTransformations;
use Illuminate\Support\Facades\Storage;

it('performs media transformations before storing files', function () {
    Storage::fake('media');

    $model = new TestTransformations;
    $model->save();

    $original = $this->getTestFile('images/800x900.png');

    $path = Storage::disk('media')
        ->putFileAs(
            'copy',
            $original,
            '800x900.jpg'
        );

    $media = $model->addMedia(
        file: Storage::disk('media')->path($path),
        collectionName: 'transform',
        disk: 'media'
    );

    Storage::disk('media')->assertExists($media->path);

    expect($media->width)->toBe(500);
    expect($media->height)->toBe(500);
});
