<?php

declare(strict_types=1);

use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\Tests\Models\TestConverters;
use Illuminate\Support\Facades\Storage;

it('converts an audio into a aac', function () {
    Storage::fake('media');

    $model = new TestConverters;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('audios/bipbip.mp3'),
        disk: 'media'
    );

    $conversion = $media->executeConversion('aac');

    expect($conversion)->toBeInstanceOf(MediaConversion::class);
    expect($conversion->mime_type)->toBe('audio/x-m4a');
    expect($conversion->extension)->toBe('m4a');

});

it('extracts an audio from a mp4 into a aac', function () {
    Storage::fake('media');

    $model = new TestConverters;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('videos/horizontal.mp4'),
        disk: 'media'
    );

    $conversion = $media->executeConversion('aac');

    expect($conversion)->toBeInstanceOf(MediaConversion::class);
    expect($conversion->mime_type)->toBe('audio/x-m4a');
    expect($conversion->extension)->toBe('m4a');

});
