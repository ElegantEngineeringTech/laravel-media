<?php

use Finller\LaravelMedia\Casts\GeneratedConversion;
use Finller\LaravelMedia\Database\Factories\MediaFactory;
use Finller\LaravelMedia\Enums\MediaType;
use Finller\LaravelMedia\Media;
use Finller\LaravelMedia\Tests\Models\Test;
use Finller\LaravelMedia\Tests\Models\TestWithNestedConversions;
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
