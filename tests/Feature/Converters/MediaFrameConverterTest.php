<?php

declare(strict_types=1);

use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\Tests\Models\TestConverters;
use Illuminate\Support\Facades\Storage;

it('extracts a frame from a video and resizes it into a jpg', function () {
    Storage::fake('media');

    $model = new TestConverters;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('videos/horizontal.mp4'),
        disk: 'media'
    );

    $conversion = $media->executeConversion('frame');

    expect($conversion)->toBeInstanceOf(MediaConversion::class);
    expect($conversion->width)->toBe(10);
    expect($conversion->extension)->toBe('jpg');

});
