<?php

use ElegantEngineeringTech\Media\Support\ResponsiveImagesConversionsPreset;
use ElegantEngineeringTech\Media\Tests\Models\TestWithVideoConversions;
use Illuminate\Support\Facades\Storage;

it('generate a poster conversion from a video', function () {
    Storage::fake('media');

    $model = new TestWithVideoConversions();
    $model->save();

    $file = $this->getTestFile('videos/horizontal.mp4');

    $model->addMedia(
        file: $file,
        collection_name: 'videos',
        disk: 'media'
    );

    $media = $model->getMedia('videos')->first();

    Storage::disk('media')->assertExists($media->path);

    $generatedConversion = $media->getGeneratedConversion('poster');

    expect($generatedConversion)->not->toBe(null);
    expect($generatedConversion->extension)->toBe('jpg');
});

it('generate a poster with its responsive images from a video', function () {
    Storage::fake('media');

    $model = new TestWithVideoConversions();
    $model->save();

    $file = $this->getTestFile('videos/horizontal.mp4');

    $model->addMedia(
        file: $file,
        collection_name: 'videos',
        disk: 'media'
    );

    $media = $model->getMedia('videos')->first();

    expect($model->getMediaConversion($media, 'poster.360'))->not->toBe(null);

    Storage::disk('media')->assertExists($media->path);

    $generatedConversion = $media->getGeneratedConversion('poster');

    expect($generatedConversion)->not->toBe(null);
    expect($generatedConversion->extension)->toBe('jpg');

    foreach (ResponsiveImagesConversionsPreset::getWidths(
        ResponsiveImagesConversionsPreset::DEFAULT_WIDTH,
        $media
    ) as $width) {
        $generatedConversion = $media->getGeneratedConversion("poster.{$width}");
        expect($generatedConversion)->not->toBe(null);
        expect($generatedConversion->width)->toBe($width);
    }
});
