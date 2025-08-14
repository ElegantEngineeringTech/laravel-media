<?php

declare(strict_types=1);

use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\Tests\Models\TestConverters;
use Illuminate\Support\Facades\Storage;

it('converts and resizes a pdf into a jpg', function () {
    Storage::fake('media');

    $model = new TestConverters;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('pdfs/dummy.pdf'),
        disk: 'media'
    );

    $conversion = $media->executeConversion('pdf');

    expect($conversion)->toBeInstanceOf(MediaConversion::class);
    expect($conversion->width)->toBe(10);
    expect($conversion->height)->toBe(10);
    expect($conversion->extension)->toBe('jpg');

});
