<?php

declare(strict_types=1);

use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\Tests\Models\TestConverters;
use Illuminate\Support\Facades\Storage;

it('converts and resizes a video into a mp4', function () {
    Storage::fake('media');

    $model = new TestConverters;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('videos/horizontal.mp4'),
        disk: 'media'
    );

    $conversion = $media->executeConversion('mp4');

    expect($conversion)->toBeInstanceOf(MediaConversion::class);
    expect($conversion->width)->toBe(10);
    expect($conversion->extension)->toBe('mp4');
    expect(round($conversion->duration))->toBe(2740.0);

});

it('converts and resizes a gif into a mp4', function () {
    Storage::fake('media');

    $model = new TestConverters;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('images/240x183.gif'),
        disk: 'media'
    );

    $conversion = $media->executeConversion('gif-mp4');

    expect($conversion)->toBeInstanceOf(MediaConversion::class);
    expect($conversion->width)->toBe(10);
    expect($conversion->extension)->toBe('mp4');

});
