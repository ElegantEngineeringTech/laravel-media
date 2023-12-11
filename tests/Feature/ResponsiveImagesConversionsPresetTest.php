<?php

use Finller\Media\Support\ResponsiveImagesConversionsPreset;
use Finller\Media\Tests\Models\TestWithResponsiveImages;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('register & generate all responsive image conversions from preset', function () {

    Storage::fake('media');

    $model = new TestWithResponsiveImages();
    $model->save();

    $orginial = UploadedFile::fake()->image('original.jpg', width: 1920, height: 1920);

    $media = $model->addMedia(
        file: $orginial,
        collection_name: 'images',
        disk: 'media'
    );

    $media->refresh();

    expect($model->getMediaConversions($media)->count())->toBe(4);
    expect($media->generated_conversions->count())->toBe(4);

    Storage::disk('media')->assertExists($media->path);

    foreach (ResponsiveImagesConversionsPreset::$widths as $width) {
        $generatedConversion = $media->getGeneratedConversion((string) $width);
        expect($generatedConversion)->not->toBe(null);
        expect($generatedConversion->width)->toBe($width);
    }

    Storage::disk('media')->assertExists($generatedConversion->path);
})->only();
