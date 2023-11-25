<?php

use FFMpeg\Coordinate\Dimension;
use Finller\LaravelMedia\Casts\GeneratedConversion;
use Finller\LaravelMedia\Database\Factories\MediaFactory;
use Finller\LaravelMedia\Enums\GeneratedConversionState;
use Finller\LaravelMedia\Enums\MediaType;
use Finller\LaravelMedia\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('retrieve the correct generated conversion key', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    expect($media->getConversionKey('poster'))->toBe('poster');
    expect($media->getConversionKey('poster.480p'))->toBe('poster.conversions.480p');
    expect($media->getConversionKey('poster.square.480p'))->toBe('poster.conversions.square.conversions.480p');
});

it('retrieve the correct generated conversion', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    $media->generated_conversions = collect([
        'poster' => MediaFactory::generatedConversion(),
    ]);

    expect($media->hasGeneratedConversion('poster'))->toBe(true);
    expect($media->hasGeneratedConversion('poster.480p'))->toBe(true);
    expect($media->hasGeneratedConversion('poster.480p.foo'))->toBe(false);

    expect($media->getGeneratedConversion('poster'))->toBeInstanceof(GeneratedConversion::class);
    expect($media->getGeneratedConversion('poster.480p'))->toBeInstanceof(GeneratedConversion::class);
    expect($media->getGeneratedConversion('poster.480p.foo'))->toBe(null);
});

it('retrieve the correct generated conversion path', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    $media->generated_conversions = collect([
        'poster' => MediaFactory::generatedConversion(),
    ]);

    expect($media->getPath('poster'))->toBe('/poster/poster.png');
    expect($media->getPath('poster.480p'))->toBe('/poster/conversions/480p/poster-480p.png');
});

it('add the correct generated conversion', function () {

    /** @var Media $media */
    $media = MediaFactory::new()->make();

    $media->generated_conversions = collect([
        'poster' => MediaFactory::generatedConversion(),
    ]);

    $media->addGeneratedConversion('optimized', new GeneratedConversion(
        file_name: 'optimized.png',
        name: 'optimized',
        state: GeneratedConversionState::Pending,
        path: '/optimized/optimized.png',
        type: MediaType::Image,
        disk: config('media.disk')
    ));

    $media->addGeneratedConversion('poster-optimized', new GeneratedConversion(
        file_name: 'poster-optimized.png',
        name: 'poster-optimized',
        state: GeneratedConversionState::Pending,
        path: 'poster/conversions/optimized/poster-optimized.png',
        type: MediaType::Image,
        disk: config('media.disk')
    ), 'poster');

    expect($media->hasGeneratedConversion('optimized'))->toBe(true);
    expect($media->hasGeneratedConversion('poster.poster-optimized'))->toBe(true);
});

it('store an uploaded image', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media');

    $file = UploadedFile::fake()->image('avatar.jpg', width: 16, height: 9);

    $media->storeFileFromUpload(
        file: $file,
        collection_name: 'avatar',
        name: 'foo',
        disk: 'media'
    );

    expect($media->width)->toBe(16);
    expect($media->height)->toBe(9);
    expect($media->aspect_ratio)->toBe((new Dimension(16, 9))->getRatio(false)->getValue());
    expect($media->collection_name)->toBe('avatar');
    expect($media->name)->toBe('foo');
    expect($media->file_name)->toBe('foo.jpg');
    expect($media->type)->toBe(MediaType::Image);
    expect($media->path)->toBe("/{$media->uuid}/foo.jpg");

    Storage::disk('media')->assertExists($media->path);
});

it('store a conversion file', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media');

    $media->storeFileFromUpload(
        file: UploadedFile::fake()->image('avatar.jpg', width: 16, height: 9),
        collection_name: 'avatar',
        name: 'avatar',
        disk: 'media'
    );

    $file = UploadedFile::fake()->image('avatar-poster.jpg', width: 16, height: 9);

    $media->storeConversion(
        file: $file->getPathname(),
        conversion: 'poster',
        name: 'avatar-poster'
    );

    $generatedConversion = $media->getGeneratedConversion('poster');

    expect($generatedConversion)->toBeInstanceof(GeneratedConversion::class);

    expect($generatedConversion->width)->toBe(16);
    expect($generatedConversion->height)->toBe(9);
    expect($generatedConversion->aspect_ratio)->toBe((new Dimension(16, 9))->getRatio(false)->getValue());
    expect($generatedConversion->name)->toBe('avatar-poster');
    expect($generatedConversion->file_name)->toBe('avatar-poster.jpg');
    expect($generatedConversion->type)->toBe(MediaType::Image);
    expect($generatedConversion->path)->toBe("/{$media->uuid}/conversions/poster/avatar-poster.jpg");
    expect($generatedConversion->path)->toBe($media->getPath('poster'));

    Storage::disk('media')->assertExists($generatedConversion->path);

    dump($media->toArray());
});
