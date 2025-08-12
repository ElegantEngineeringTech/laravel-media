<?php

declare(strict_types=1);

use Elegantly\Media\Converters\Video\MediaMp4Converter;
use Elegantly\Media\Tests\Models\Test;
use Elegantly\Media\Tests\Models\TestVideo;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('dispatches the queued conversions when a media is added', function () {
    Queue::fake();
    Storage::fake('media');

    $model = new TestVideo;
    $model->save();

    $model->addMedia(
        file: $this->getTestFile('videos/horizontal.mp4'),
        collectionName: 'queued',
        disk: 'media'
    );

    Queue::assertPushed(MediaMp4Converter::class, 1);
});

it('generates unqueued conversions immediatly when a media is added', function () {
    Queue::fake();
    Storage::fake('media');

    $model = new TestVideo;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('videos/horizontal.mp4'),
        collectionName: 'unqueued',
        disk: 'media'
    );

    Queue::assertPushed(MediaMp4Converter::class, 0);

    $conversion = $media->getConversion('small');

    expect($conversion)->not->toBe(null);

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
