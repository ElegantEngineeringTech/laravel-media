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

it('get the correct media collection', function () {
    $model = new Test();

    expect($model->getMediaCollections()->has('files'))->toBe(true);
});

it('get the correct media conversion', function () {
    $model = new TestWithNestedConversions();

    /** @var Media $media */
    $media = MediaFactory::new()->make([
        'type' => MediaType::Image,
    ]);

    expect($model->getMediaConversion($media, 'optimized')?->name)->toBe('optimized');
    expect($model->getMediaConversion($media, 'optimized.webp')?->name)->toBe('webp');
});

it('create a media, store files and generate conversions', function () {
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

it('generate nested conversions', function () {
    Storage::fake('media');

    $model = new TestWithNestedConversions();
    $model->save();

    $file = UploadedFile::fake()->image('foo.jpg');

    $media = $model->addMedia(
        file: $file,
        disk: 'media'
    );

    expect($media->collection_name)->toBe(config('media.default_collection_name'));

    $media = $model->getMedia()->first();

    expect($media->generated_conversions->count())->toBe(1);

    // Parent conversion
    $generatedConversion = $media->getGeneratedConversion('optimized');

    expect($generatedConversion)->not->toBe(null);

    Storage::disk('media')->assertExists($generatedConversion->path);

    // Child conversion
    $childGeneratedConversion = $media->getGeneratedConversion('optimized.webp');
    expect($generatedConversion->generated_conversions->count())->toBe(1);
    expect($childGeneratedConversion)->toBeInstanceOf(GeneratedConversion::class);
    expect($childGeneratedConversion->extension)->toBe('webp');

    Storage::disk('media')->assertExists($childGeneratedConversion->path);
});

it('get the fallback value when no media extist', function () {
    $model = new Test();

    expect($model->getFirstMediaUrl('fallback'))->toBe('fallback-value');
});

it('get the media url when a media exists in a collection', function () {
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
