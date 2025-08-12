<?php

declare(strict_types=1);

use Elegantly\Media\Converters\Image\MediaImageConverter;
use Elegantly\Media\Tests\Models\TestImage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('generates a image conversion immediatly when a media is added', function () {
    Queue::fake();
    Storage::fake('media');

    $model = new TestImage;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('images/1.jpg'),
        collectionName: 'images',
        disk: 'media'
    );

    Queue::assertPushed(MediaImageConverter::class, 0);

    $conversion = $media->getConversion('small');

    expect($conversion)->not->toBe(null);

    expect($media->conversions)->toHaveCount(1);

});

it('generates nested image conversion immediatly when a media is added', function () {
    Queue::fake();
    Storage::fake('media');

    $model = new TestImage;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('images/1.jpg'),
        collectionName: 'nested-images',
        disk: 'media'
    );

    Queue::assertPushed(MediaImageConverter::class, 0);

    $small = $media->getConversion('small');
    expect($small)->not->toBe(null);

    $smaller = $media->getConversion('small.smaller');
    expect($smaller)->not->toBe(null);

    expect($media->conversions)->toHaveCount(2);

});

it('does not generate non-immediate nested image conversion immediatly when a media is added', function () {
    Queue::fake();
    Storage::fake('media');

    $model = new TestImage;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('images/1.jpg'),
        collectionName: 'delayed-nested-images',
        disk: 'media'
    );

    Queue::assertPushed(MediaImageConverter::class, 0);

    expect($media->conversions)->toHaveCount(0);

});

it('generates non-immediate parent nested conversion on demand', function () {
    Queue::fake();
    Storage::fake('media');

    $model = new TestImage;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('images/1.jpg'),
        collectionName: 'delayed-nested-images',
        disk: 'media'
    );

    Queue::assertPushed(MediaImageConverter::class, 0);

    $media->executeConversion('small.smaller');

    $small = $media->getConversion('small');
    expect($small)->not->toBe(null);

    $smaller = $media->getConversion('small.smaller');
    expect($smaller)->not->toBe(null);

    expect($media->conversions)->toHaveCount(2);

});

it('generates non-immediate parent with non-immediate nested conversion on demand', function () {
    Queue::fake();
    Storage::fake('media');

    $model = new TestImage;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('images/1.jpg'),
        collectionName: 'delayed-nested-images',
        disk: 'media'
    );

    Queue::assertPushed(MediaImageConverter::class, 0);

    $media->executeConversion('small.smaller');

    $small = $media->getConversion('small');
    expect($small)->not->toBe(null);

    $smaller = $media->getConversion('small.smaller');
    expect($smaller)->not->toBe(null);

    expect($media->conversions)->toHaveCount(2);

});

it('generates non-immediate parent with immediate nested conversion on demand', function () {
    Queue::fake();
    Storage::fake('media');

    $model = new TestImage;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('images/1.jpg'),
        collectionName: 'delayed-immediate-nested-images',
        disk: 'media'
    );

    Queue::assertPushed(MediaImageConverter::class, 0);

    $media->executeConversion('small.smaller');

    $small = $media->getConversion('small');
    expect($small)->not->toBe(null);

    $smaller = $media->getConversion('small.smaller');
    expect($smaller)->not->toBe(null);

    expect($media->conversions)->toHaveCount(2);

})->only();
