<?php

use Finller\Media\Casts\GeneratedConversion;
use Finller\Media\Database\Factories\MediaFactory;
use Finller\Media\Enums\MediaType;
use Finller\Media\Models\Media;
use Finller\Media\Tests\Models\Test;
use Finller\Media\Tests\Models\TestSoftDelete;
use Finller\Media\Tests\Models\TestWithNestedConversions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('gets the correct media collection', function () {
    $model = new Test();

    expect($model->getMediaCollections()->toArray())->toHaveKeys(['files', 'avatar', 'fallback']);
});

it('keys media conversion by conversionName', function () {
    $model = new TestWithNestedConversions();

    /** @var Media $media */
    $media = MediaFactory::new()->make([
        'type' => MediaType::Image,
    ]);

    expect($model->getMediaConversions($media)->toArray())->toHaveKeys(['optimized', '360']);
});

it('gets the correct media conversion', function () {
    $model = new TestWithNestedConversions();

    /** @var Media $media */
    $media = MediaFactory::new()->make([
        'type' => MediaType::Image,
    ]);

    expect($model->getMediaConversion($media, 'optimized')?->conversionName)->toBe('optimized');
    expect($model->getMediaConversion($media, '360')?->conversionName)->toBe('360');
});

it('gets the correct nested media conversion', function () {
    $model = new TestWithNestedConversions();

    /** @var Media $media */
    $media = MediaFactory::new()->make([
        'type' => MediaType::Image,
        /**
         * In order to access the nested '360' conversion, the parent one must be already generated
         */
        'generated_conversions' => [
            'optimized' => new GeneratedConversion(
                name: 'optimized',
                file_name: 'optimized.jpg',
            ),
        ],
    ]);

    expect($media->getGeneratedConversion('optimized'))->not->toBe(null);

    expect($model->getMediaConversion($media, 'optimized.webp')?->conversionName)->toBe('webp');
});

it('creates a media, store files and generate conversions', function () {
    Storage::fake('media');

    $model = new Test();
    $model->save();

    $file = UploadedFile::fake()->image('foo.jpg');

    $media = $model->addMedia(
        file: $file,
        collection_name: 'files',
        disk: 'media'
    );

    $media->refresh();

    expect($model->getMediaConversions($media)->count())->toBe(1);

    expect($media->collection_name)->toBe('files');

    Storage::disk('media')->assertExists($media->path);

    expect($media->generated_conversions->count())->toBe(1);

    $generatedConversion = $media->getGeneratedConversion('optimized');

    expect($generatedConversion)->not->toBe(null);

    Storage::disk('media')->assertExists($generatedConversion->path);
});

it('generates nested conversions', function () {
    Storage::fake('media');

    $model = new TestWithNestedConversions();
    $model->save();

    $file = UploadedFile::fake()->image('foo.jpg');

    $media = $model->addMedia(
        file: $file,
        disk: 'media'
    );

    $media->refresh();
    expect($media->collection_name)->toBe(config('media.default_collection_name'));

    $mediaConversions = $model->getMediaConversions($media);

    expect($media->generated_conversions->toArray())
        ->toHaveLength($mediaConversions->count())
        ->toHaveKeys($mediaConversions->keys()->toArray());

    // Parent conversion
    $generatedConversion = $media->getGeneratedConversion('optimized');

    expect($generatedConversion)->toBeInstanceOf(GeneratedConversion::class);
    expect($generatedConversion->name)->toBe('optimized');

    Storage::disk('media')->assertExists($generatedConversion->path);

    expect($generatedConversion->generated_conversions->toArray())
        ->toHaveLength(1)
        ->toHaveKeys(['webp']);

    // Child conversion
    $childGeneratedConversion = $media->getGeneratedConversion('optimized.webp');
    expect($childGeneratedConversion)->toBeInstanceOf(GeneratedConversion::class);
    expect($childGeneratedConversion->extension)->toBe('webp');
    expect($childGeneratedConversion->name)->toBe('optimized');

    Storage::disk('media')->assertExists($childGeneratedConversion->path);
});

it('gets the fallback value when no media extist', function () {
    $model = new Test();

    expect($model->getFirstMediaUrl('fallback'))->toBe('fallback-value');
});

it('gets the media url when a media exists in a collection', function () {
    Storage::fake('media');

    $model = new Test();
    $model->save();

    $file = UploadedFile::fake()->image('foo.jpg');

    $media = $model->addMedia(
        file: $file,
        collection_name: 'fallback',
        disk: 'media'
    );

    expect($model->getFirstMedia()->id)->toBe($media->id);
    expect($model->getFirstMediaUrl())->toBe($media->getUrl());
});

it('adds the new added media to the model relation', function () {
    Storage::fake('media');

    $model = new Test();
    $model->save();

    $model->load('media');

    expect($model->media)->toHaveLength(0);

    $model->addMedia(
        file: UploadedFile::fake()->image('foo.jpg'),
        collection_name: 'files',
        disk: 'media'
    );

    expect($model->media)->toHaveLength(1);

    $model->addMedia(
        file: UploadedFile::fake()->image('bar.jpg'),
        collection_name: 'fallback',
        disk: 'media'
    );

    expect($model->media)->toHaveLength(2);
});

it('removes media from the model when clearing media collection', function () {
    Storage::fake('media');

    $model = new Test();
    $model->save();

    $model->addMedia(
        file: UploadedFile::fake()->image('foo.jpg'),
        collection_name: 'files',
        disk: 'media'
    );

    $model->addMedia(
        file: UploadedFile::fake()->image('bar.jpg'),
        collection_name: 'fallback',
        disk: 'media'
    );

    expect($model->media)->toHaveLength(2);
    expect($model->getMedia('files'))->toHaveLength(1);

    $model->clearMediaCollection('files');

    expect($model->media)->toHaveLength(1);
    expect($model->getMedia('files'))->toHaveLength(0);
});

it('deletes media and its files with the model when delete_media_with_model is true', function () {
    config()->set('media.delete_media_with_model', true);

    Storage::fake('media');

    $model = new Test();
    $model->save();

    $file = UploadedFile::fake()->image('foo.jpg');

    $media = $model->addMedia(
        file: $file,
        collection_name: 'fallback',
        disk: 'media'
    );

    Storage::disk('media')->assertExists($media->path);

    $model->delete();

    expect($media->fresh())->toBe(null);

    Storage::disk('media')->assertMissing($media->path);
});

it('does not delete media and its files with the model when delete_media_with_model is false', function () {
    config()->set('media.delete_media_with_model', false);

    Storage::fake('media');

    $model = new Test();
    $model->save();

    $file = UploadedFile::fake()->image('foo.jpg');

    $media = $model->addMedia(
        file: $file,
        collection_name: 'fallback',
        disk: 'media'
    );

    Storage::disk('media')->assertExists($media->path);

    $model->delete();

    expect($media->fresh()?->exists)->toBe(true);

    Storage::disk('media')->assertExists($media->path);
});

it('deletes media and its files with the trashed model when delete_media_with_trashed_model is true', function () {
    config()->set('media.delete_media_with_model', true);
    config()->set('media.delete_media_with_trashed_model', true);

    Storage::fake('media');

    $model = new TestSoftDelete();
    $model->save();

    $file = UploadedFile::fake()->image('foo.jpg');

    $media = $model->addMedia(
        file: $file,
        collection_name: 'fallback',
        disk: 'media'
    );

    Storage::disk('media')->assertExists($media->path);

    $model->delete();

    expect($media->fresh())->toBe(null);

    Storage::disk('media')->assertMissing($media->path);
});

it('does not delete media and its files with the trashed model when delete_media_with_trashed_model is false', function () {
    config()->set('media.delete_media_with_model', true);
    config()->set('media.delete_media_with_trashed_model', false);

    Storage::fake('media');

    $model = new TestSoftDelete();
    $model->save();

    $file = UploadedFile::fake()->image('foo.jpg');

    $media = $model->addMedia(
        file: $file,
        collection_name: 'fallback',
        disk: 'media'
    );

    Storage::disk('media')->assertExists($media->path);

    $model->delete();

    expect($media->fresh()?->exists)->toBe(true);

    Storage::disk('media')->assertExists($media->path);
});

it('deletes media and its files with the force deleted model when delete_media_with_trashed_model is false', function () {
    config()->set('media.delete_media_with_model', true);
    config()->set('media.delete_media_with_trashed_model', false);

    Storage::fake('media');

    $model = new TestSoftDelete();
    $model->save();

    $file = UploadedFile::fake()->image('foo.jpg');

    $media = $model->addMedia(
        file: $file,
        collection_name: 'fallback',
        disk: 'media'
    );

    Storage::disk('media')->assertExists($media->path);

    $model->forceDelete();

    expect($media->fresh())->toBe(null);

    Storage::disk('media')->assertMissing($media->path);
});
