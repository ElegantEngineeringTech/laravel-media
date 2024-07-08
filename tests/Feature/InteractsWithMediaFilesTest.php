<?php

use Elegantly\Media\Database\Factories\MediaFactory;
use Elegantly\Media\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File as SupportFile;
use Illuminate\Support\Facades\Storage;
use Spatie\TemporaryDirectory\TemporaryDirectory;

it('copy the Media file to a temporary directory', function () {

    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media');

    $file = UploadedFile::fake()->image('foo.jpg');

    $media->storeFile(
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

    $media->storeFile(
        file: $file,
        disk: 'media'
    );

    $poster = UploadedFile::fake()->image('foo-poster.jpg');

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

it('put file to the Media path', function () {

    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media');

    $file = UploadedFile::fake()->image('foo.jpg');

    $media->storeFile(
        file: $file,
        disk: 'media'
    );

    $otherFile = UploadedFile::fake()->image('foo-other.jpg');

    $path = $media->putFile($otherFile);

    Storage::disk('media')->assertExists($path);

    expect(SupportFile::dirname($path))->toBe($media->getDirname());
});

it('put file to the Generated conversion path', function () {

    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media');

    $file = UploadedFile::fake()->image('foo.jpg');

    $media->storeFile(
        file: $file,
        disk: 'media'
    );

    $poster = UploadedFile::fake()->image('foo-poster.jpg');

    $generatedConversion = $media->storeConversion(
        file: $poster->getPathname(),
        conversion: 'poster',
        name: 'avatar-poster'
    );

    $otherFile = UploadedFile::fake()->image('foo-other.jpg');

    $path = $generatedConversion->putFile($otherFile);

    Storage::disk('media')->assertExists($path);

    expect(SupportFile::dirname($path))->toBe($generatedConversion->getDirname());
});

it('moves the main file to a new path', function () {

    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media-from');
    Storage::fake('media-to');

    $file = UploadedFile::fake()->image('foo.jpg');

    $media->storeFile(
        file: $file,
        disk: 'media-from'
    );

    Storage::disk('media-from')->assertExists($media->path);

    $media->moveFile(
        disk: 'media-to'
    );

    expect($media->disk)->toBe('media-to');

    Storage::disk('media-from')->assertMissing($media->path);
    Storage::disk('media-to')->assertExists($media->path);
});

it('moves a conversion file to a new path', function () {

    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media-from');
    Storage::fake('media-to');

    $file = UploadedFile::fake()->image('foo.jpg');

    $media->storeFile(
        file: $file,
        disk: 'media-from'
    );

    $poster = UploadedFile::fake()->image('foo-poster.jpg');

    $generatedConversion = $media->storeConversion(
        file: $poster->getPathname(),
        conversion: 'poster',
        name: 'avatar-poster'
    );

    Storage::disk('media-from')->assertExists($generatedConversion->path);

    $media->moveGeneratedConversion(
        conversion: 'poster',
        disk: 'media-to'
    );

    expect($generatedConversion->disk)->toBe('media-to');

    Storage::disk('media-from')->assertMissing($media->getPath('poster'));
    Storage::disk('media-to')->assertExists($media->getPath('poster'));
});

it('moves media to a new disk', function () {

    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media-from');
    Storage::fake('media-to');

    $file = UploadedFile::fake()->image('foo.jpg');

    $media->storeFile(
        file: $file,
        disk: 'media-from'
    );

    $poster = UploadedFile::fake()->image('foo-poster.jpg');

    $generatedConversion = $media->storeConversion(
        file: $poster->getPathname(),
        conversion: 'poster',
        name: 'avatar-poster'
    );

    Storage::disk('media-from')->assertExists($media->path);
    Storage::disk('media-from')->assertExists($generatedConversion->path);

    $media->moveToDisk(
        disk: 'media-to'
    );

    expect($media->disk)->toBe('media-to');
    expect($generatedConversion->disk)->toBe('media-to');

    Storage::disk('media-from')->assertMissing($media->path);
    Storage::disk('media-from')->assertMissing($media->getPath('poster'));
    Storage::disk('media-to')->assertExists($media->path);
    Storage::disk('media-to')->assertExists($media->getPath('poster'));
});
