<?php

declare(strict_types=1);

use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\Tests\Models\TestConverters;
use Illuminate\Support\Facades\Storage;

it('converts an audio into a mp3', function () {
    Storage::fake('media');

    $model = new TestConverters;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('audios/bipbip.mp3'),
        disk: 'media'
    );

    $conversion = $media->executeConversion('mp3');

    expect($conversion)->toBeInstanceOf(MediaConversion::class);
    expect($conversion->extension)->toBe('mp3');

});

it('extracts an audio from a mp4 into a mp3', function () {
    Storage::fake('media');

    $model = new TestConverters;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('videos/horizontal.mp4'),
        disk: 'media'
    );

    $conversion = $media->executeConversion('mp3');

    expect($conversion)->toBeInstanceOf(MediaConversion::class);
    expect($conversion->extension)->toBe('mp3');

});
