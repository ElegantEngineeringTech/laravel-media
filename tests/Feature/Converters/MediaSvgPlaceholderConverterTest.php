<?php

declare(strict_types=1);

use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\Tests\Models\TestConverters;
use Illuminate\Support\Facades\Storage;

it('converts an image into a base64 svg placeholder', function () {
    Storage::fake('media');

    $model = new TestConverters;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('images/1.jpg'),
        disk: 'media'
    );

    $conversion = $media->executeConversion('svg');

    expect($conversion)->toBeInstanceOf(MediaConversion::class);
    expect($conversion->contents)->not->toBe(null);
    expect($conversion->size)->not->toBe(null);

});
