<?php

declare(strict_types=1);

use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\Tests\Models\TestConverters;
use Illuminate\Support\Facades\Storage;

it('converts and resizes an image into a jpg', function () {
    Storage::fake('media');

    $model = new TestConverters;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('images/1.jpg'),
        disk: 'media'
    );

    $conversion = $media->executeConversion('jpg');

    expect($conversion)->toBeInstanceOf(MediaConversion::class);
    expect($conversion->width)->toBe(10);
    expect($conversion->height)->toBe(10);
    expect($conversion->extension)->toBe('jpg');

});
