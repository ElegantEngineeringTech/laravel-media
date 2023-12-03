<?php

use Finller\Media\Database\Factories\MediaFactory;
use Finller\Media\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\TemporaryDirectory\TemporaryDirectory;

it('copy the Media file to a temporary directory', function () {

    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media');

    $file = UploadedFile::fake()->image('foo.jpg');

    $media->storeFileFromUpload(
        file: $file,
        disk: 'media'
    );

    expect($media->getDisk()->exists($media->path))->toBe(true);

    $temporaryDirectory = (new TemporaryDirectory())
        ->location(storage_path('media-tmp'))
        ->create();

    $path = $media->makeTemporaryFileCopy($temporaryDirectory);

    expect($path)->toBeString();

    expect(is_file($path))->tobe(true);

    $temporaryDirectory->delete();

    expect(is_file($path))->tobe(false);
});

it('copy the GeneratedConversion file to a temporary directory', function () {

    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media');

    $file = UploadedFile::fake()->image('foo.jpg');

    $media->storeFileFromUpload(
        file: $file,
        disk: 'media'
    );

    $poster = UploadedFile::fake()->image('foo-poster.jpg', width: 16, height: 9);

    $generatedConversion = $media->storeConversion(
        file: $poster->getPathname(),
        conversion: 'poster',
        name: 'avatar-poster'
    );

    expect($generatedConversion->getDisk()->exists($generatedConversion->path))->toBe(true);

    $temporaryDirectory = (new TemporaryDirectory())
        ->location(storage_path('media-tmp'))
        ->create();

    $path = $generatedConversion->makeTemporaryFileCopy($temporaryDirectory);

    expect($path)->toBeString();

    expect(is_file($path))->tobe(true);

    $temporaryDirectory->delete();

    expect(is_file($path))->tobe(false);
});
