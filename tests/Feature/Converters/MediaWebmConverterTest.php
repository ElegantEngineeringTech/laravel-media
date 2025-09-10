<?php

declare(strict_types=1);

use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\Tests\Models\TestConverters;
use Illuminate\Support\Facades\Storage;

it('converts and resizes a video into a webm', function () {
    Storage::fake('media');

    $model = new TestConverters;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('videos/horizontal.mp4'),
        disk: 'media'
    );

    $conversion = $media->executeConversion('webm');

    expect($conversion)->toBeInstanceOf(MediaConversion::class);
    expect($conversion->width)->toBe(10);
    expect($conversion->extension)->toBe('webm');

});
