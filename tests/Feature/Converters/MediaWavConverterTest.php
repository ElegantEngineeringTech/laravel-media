<?php

declare(strict_types=1);

use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\Tests\Models\TestConverters;
use Illuminate\Support\Facades\Storage;

it('converts an audio into a wav', function () {
    Storage::fake('media');

    $model = new TestConverters;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('audios/bipbip.mp3'),
        disk: 'media'
    );

    $conversion = $media->executeConversion('wav');

    expect($conversion)->toBeInstanceOf(MediaConversion::class);
    expect($conversion->extension)->toBe('wav');

});

it('extracts an audio from a mp4 into a wav', function () {
    Storage::fake('media');

    $model = new TestConverters;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('videos/horizontal.mp4'),
        disk: 'media'
    );

    $conversion = $media->executeConversion('wav');

    expect($conversion)->toBeInstanceOf(MediaConversion::class);
    expect($conversion->extension)->toBe('wav');

});
