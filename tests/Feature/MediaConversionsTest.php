<?php

declare(strict_types=1);

use Elegantly\Media\Converters\Image\MediaImageConverter;
use Elegantly\Media\Events\MediaConverterExecutedEvent;
use Elegantly\Media\Tests\Models\TestConversions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('does not generates non immediate conversion when a media is added', function () {
    Queue::fake();
    Storage::fake('media');

    $model = new TestConversions;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('images/1.jpg'),
        collectionName: 'simple',
        disk: 'media'
    );

    Queue::assertPushed(MediaImageConverter::class, 0);

    $conversion = $media->getConversion('small');

    expect($conversion)->toBe(null);

    expect($media->conversions)->toHaveCount(0);
});

it('generates immediate conversion when a media is added', function () {
    Event::fake();
    Queue::fake();
    Storage::fake('media');

    $model = new TestConversions;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('images/1.jpg'),
        collectionName: 'simple-immediate',
        disk: 'media'
    );

    Queue::assertPushed(MediaImageConverter::class, 0);
    Event::assertDispatched(MediaConverterExecutedEvent::class, 1);

    $conversion = $media->getConversion('small');

    expect($conversion)->not->toBe(null);

    expect($media->conversions)->toHaveCount(1);
});

it('does not queue non immediate queued conversion when a media is added', function () {
    Event::fake();
    Queue::fake();
    Storage::fake('media');

    $model = new TestConversions;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('images/1.jpg'),
        collectionName: 'simple-queued',
        disk: 'media'
    );

    Queue::assertPushed(MediaImageConverter::class, 0);
    Event::assertDispatched(MediaConverterExecutedEvent::class, 0);

    $conversion = $media->getConversion('small');

    expect($conversion)->toBe(null);

    expect($media->conversions)->toHaveCount(0);
});

it('does queue immediate queued conversion when a media is added', function () {
    Event::fake();
    Queue::fake();
    Storage::fake('media');

    $model = new TestConversions;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('images/1.jpg'),
        collectionName: 'simple-immediate-queued',
        disk: 'media'
    );

    Queue::assertPushed(MediaImageConverter::class, 1);
    Event::assertDispatched(MediaConverterExecutedEvent::class, 0);

    $conversion = $media->getConversion('small');

    expect($conversion)->toBe(null);

    expect($media->conversions)->toHaveCount(0);
});

it('generates immediate nested conversion when a media is added', function () {
    Event::fake();
    Queue::fake();
    Storage::fake('media');

    $model = new TestConversions;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('images/1.jpg'),
        collectionName: 'immediate-nested-immediate',
        disk: 'media'
    );

    Queue::assertPushed(MediaImageConverter::class, 0);
    Event::assertDispatched(MediaConverterExecutedEvent::class, 2);

    $small = $media->getConversion('small');
    $smaller = $media->getConversion('small.smaller');

    expect($small)->not->toBe(null);
    expect($smaller)->not->toBe(null);

    expect($media->conversions)->toHaveCount(2);
});

it('does queue immediate queued nested conversion when a media is added', function () {
    Event::fake();
    Queue::fake();
    Storage::fake('media');

    $model = new TestConversions;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('images/1.jpg'),
        collectionName: 'immediate-nested-immediate-queued',
        disk: 'media'
    );

    Queue::assertPushed(MediaImageConverter::class, 1);
    Event::assertDispatched(MediaConverterExecutedEvent::class, 1);

    $small = $media->getConversion('small');
    $smaller = $media->getConversion('small.smaller');

    expect($small)->not->toBe(null);
    expect($smaller)->toBe(null);

    expect($media->conversions)->toHaveCount(1);
});

it('generates non existant parent conversion when a child is executed', function () {
    Event::fake();
    Queue::fake();
    Storage::fake('media');

    $model = new TestConversions;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('images/1.jpg'),
        collectionName: 'nested',
        disk: 'media'
    );

    $media->executeConversion('small.smaller');

    Queue::assertPushed(MediaImageConverter::class, 0);
    Event::assertDispatched(MediaConverterExecutedEvent::class, 2);

    $small = $media->getConversion('small');
    $smaller = $media->getConversion('small.smaller');

    expect($small)->not->toBe(null);
    expect($smaller)->not->toBe(null);

    expect($media->conversions)->toHaveCount(2);
});

it('generates immediate children conversion when a parent is executed', function () {
    Event::fake();
    Queue::fake();
    Storage::fake('media');

    $model = new TestConversions;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('images/1.jpg'),
        collectionName: 'nested-immediate',
        disk: 'media'
    );

    $media->executeConversion('small');

    Queue::assertPushed(MediaImageConverter::class, 0);
    Event::assertDispatched(MediaConverterExecutedEvent::class, 2);

    $small = $media->getConversion('small');
    $smaller = $media->getConversion('small.smaller');

    expect($small)->not->toBe(null);
    expect($smaller)->not->toBe(null);

    expect($media->conversions)->toHaveCount(2);
});
