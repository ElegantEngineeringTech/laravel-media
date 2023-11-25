<?php

use FFMpeg\Coordinate\Dimension;
use Finller\LaravelMedia\Casts\GeneratedConversion;
use Finller\LaravelMedia\Database\Factories\MediaFactory;
use Finller\LaravelMedia\Enums\MediaType;
use Finller\LaravelMedia\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('retrieve the generated conversion key', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    expect($media->getConversionKey('poster'))->toBe('poster');
    expect($media->getConversionKey('poster.480p'))->toBe('poster.conversions.480p');
    expect($media->getConversionKey('poster.square.480p'))->toBe('poster.conversions.square.conversions.480p');
});

it('retrieve the generated conversion', function () {
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

it('retrieve the generated conversion path', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    $media->generated_conversions = collect([
        'poster' => MediaFactory::generatedConversion(),
    ]);

    expect($media->getPath('poster'))->toBe('/poster/poster.png');
    expect($media->getPath('poster.480p'))->toBe('/poster/conversions/480p/poster-480p.png');
});

it('add the generated conversion', function () {

    /** @var Media $media */
    $media = MediaFactory::new()->make();

    $media->generated_conversions = collect([
        'poster' => MediaFactory::generatedConversion(),
    ]);

    $media->addGeneratedConversion('optimized', new GeneratedConversion(
        file_name: 'optimized.png',
        name: 'optimized',
        state: 'pending',
        path: '/optimized/optimized.png',
        type: MediaType::Image,
        disk: config('media.disk')
    ));

    $media->addGeneratedConversion('poster.poster-optimized', new GeneratedConversion(
        file_name: 'poster-optimized.png',
        name: 'poster-optimized',
        state: 'pending',
        path: 'poster/conversions/optimized/poster-optimized.png',
        type: MediaType::Image,
        disk: config('media.disk')
    ));

    expect($media->hasGeneratedConversion('optimized'))->toBe(true);
    expect($media->hasGeneratedConversion('poster.poster-optimized'))->toBe(true);
});

it('update a conversion', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    $media->generated_conversions = collect([
        'poster' => MediaFactory::generatedConversion(),
    ]);

    $generatedConversion = $media->getGeneratedConversion('poster');
    $media->save();

    expect($generatedConversion->state)->tobe('success');

    $generatedConversion->state = 'failure';
    $media->save();

    $media->refresh();

    expect($generatedConversion->state)->tobe('failure');
});

it('store an uploaded image', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media');

    $file = UploadedFile::fake()->image('foo.jpg', width: 16, height: 9);

    $media->storeFileFromUpload(
        file: $file,
        collection_name: 'avatar',
        name: 'avatar',
        disk: 'media'
    );

    expect($media->width)->toBe(16);
    expect($media->height)->toBe(9);
    expect($media->aspect_ratio)->toBe((new Dimension(16, 9))->getRatio(false)->getValue());
    expect($media->collection_name)->toBe('avatar');
    expect($media->name)->toBe('avatar');
    expect($media->file_name)->toBe('avatar.jpg');
    expect($media->type)->toBe(MediaType::Image);
    expect($media->path)->toBe("/{$media->uuid}/avatar.jpg");

    Storage::disk('media')->assertExists($media->path);
});

it('store a conversion image of a media', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media');

    $orginial = UploadedFile::fake()->image('foo.jpg', width: 16, height: 9);

    $media->storeFileFromUpload(
        file: $orginial,
        collection_name: 'avatar',
        name: 'avatar',
        disk: 'media'
    );

    $poster = UploadedFile::fake()->image('foo-poster.jpg', width: 16, height: 9);

    $media->storeConversion(
        file: $poster->getPathname(),
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
});

it('store a conversion image of a conversion', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media');

    $media->storeFileFromUpload(
        file: UploadedFile::fake()->image('foo.jpg', width: 16, height: 9),
        collection_name: 'avatar',
        name: 'avatar',
        disk: 'media'
    );

    $poster = UploadedFile::fake()->image('foo-poster.jpg', width: 16, height: 9);

    $media->storeConversion(
        file: $poster->getPathname(),
        conversion: 'poster',
        name: 'avatar-poster'
    );

    $small = UploadedFile::fake()->image('foo-poster-small.jpg', width: 16, height: 9);

    $media->storeConversion(
        file: $small->getPathname(),
        conversion: 'poster.small',
        name: 'avatar-poster-small'
    );

    $generatedConversion = $media->getGeneratedConversion('poster.small');

    expect($generatedConversion)->toBeInstanceof(GeneratedConversion::class);

    expect($generatedConversion->width)->toBe(16);
    expect($generatedConversion->height)->toBe(9);
    expect($generatedConversion->aspect_ratio)->toBe((new Dimension(16, 9))->getRatio(false)->getValue());
    expect($generatedConversion->name)->toBe('avatar-poster-small');
    expect($generatedConversion->file_name)->toBe('avatar-poster-small.jpg');
    expect($generatedConversion->type)->toBe(MediaType::Image);
    expect($generatedConversion->path)->toBe("/{$media->uuid}/conversions/poster/conversions/small/avatar-poster-small.jpg");
    expect($generatedConversion->path)->toBe($media->getPath('poster.small'));

    Storage::disk('media')->assertExists($generatedConversion->path);
});
