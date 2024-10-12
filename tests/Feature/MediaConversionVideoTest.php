<?php

use Elegantly\Media\Jobs\MediaConversionJob;
use Elegantly\Media\Tests\Models\Test;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('video conversion is dispatched when adding media', function () {
    Queue::fake();
    Storage::fake('media');
    $model = new Test;
    $model->save();

    $model->addMedia(
        file: $this->getTestFile('videos/horizontal.mp4'),
        collectionName: 'conversions',
        disk: 'media'
    );

    Queue::assertPushed(MediaConversionJob::class, 1);
});

it('generates video conversion when adding media', function () {
    Storage::fake('media');
    $model = new Test;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('videos/horizontal.mp4'),
        collectionName: 'conversions',
        disk: 'media'
    );

    // because some conversions are queued
    $media->refresh();

    $conversion = $media->getConversion('small');

    expect($conversion)->not->toBe(null);
    expect($conversion->width)->toBe(100);
    expect($conversion->aspect_ratio)->toBe($media->aspect_ratio);

});
