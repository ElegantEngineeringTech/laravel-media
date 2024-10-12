<?php

use Elegantly\Media\MediaCollection;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Tests\Models\Test;
use Elegantly\Media\Tests\Models\TestSoftDelete;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('gets the correct media collection', function () {
    $model = new Test;

    $collection = $model->getMediaCollection('single');
    expect($collection)->toBeInstanceOf(MediaCollection::class);
    expect($collection->name)->toBe('single');
});

it('gets the fallback value when no media extist', function () {
    $model = new Test;

    expect($model->getFirstMediaUrl('fallback'))->toBe('fallback-value');
});

it('retreives the media url', function () {
    Storage::fake('media');
    $model = new Test;
    $model->save();

    $model->addMedia(
        file: UploadedFile::fake()->image('foo.jpg'),
        disk: 'media',
        collectionName: 'files',
    );

    expect($model->getFirstMediaUrl('files'))->not->toBe(null);

});

it('adds a new media to the default collection', function () {
    Storage::fake('media');
    $model = new Test;
    $model->save();

    $file = UploadedFile::fake()->image('foo.jpg', width: 16, height: 9);

    $media = $model->addMedia(
        file: $file,
        disk: 'media'
    );

    expect($media->model_id)->toBe($model->id);
    expect($media->model_type)->toBe(get_class($model));
    expect($media->exists)->toBe(true);
    expect($media->name)->toBe('foo');
    expect($media->extension)->toBe('jpg');
    expect($media->file_name)->toBe('foo.jpg');

    expect($media->collection_name)->toBe(config('media.default_collection_name'));
    expect($media->collection_group)->toBe(null);

    Storage::disk('media')->assertExists($media->path);

    expect($model->media)->toHaveLength(1);

    $modelMedia = $model->getFirstMedia();

    expect($modelMedia)->toBeInstanceOf(Media::class);
});

it('adds a new media to a collection and group', function () {
    Storage::fake('media');
    $model = new Test;
    $model->save();

    $file = UploadedFile::fake()->image('foo.jpg', width: 16, height: 9);

    $media = $model->addMedia(
        file: $file,
        collectionName: 'files',
        collectionGroup: 'group',
        disk: 'media'
    );

    Storage::disk('media')->assertExists($media->path);

    expect($media->model_id)->toBe($model->id);
    expect($media->model_type)->toBe(get_class($model));
    expect($media->exists)->toBe(true);
    expect($media->collection_name)->toBe('files');
    expect($media->collection_group)->toBe('group');

    expect($model->media)->toHaveLength(1);

    $modelMedia = $model->getFirstMedia();

    expect($modelMedia)->toBeInstanceOf(Media::class);
});

it('generates conversions and nested conversions when adding media', function () {
    Storage::fake('media');
    $model = new Test;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('videos/horizontal.mp4'),
        collectionName: 'conversions',
        disk: 'media'
    );

    expect(
        $media->conversions->pluck('conversion_name')->toArray()
    )->toBe([
        'poster',
        'poster.360',
        // 'small' video conversion is queued
    ]);

});

it('deletes old media when adding to single collection', function () {
    Storage::fake('media');
    $model = new Test;
    $model->save();

    $firstMedia = $model->addMedia(
        file: UploadedFile::fake()->image('foo.jpg'),
        disk: 'media',
        collectionName: 'single',
    );

    Storage::disk('media')->assertExists($firstMedia->path);

    expect($model->media)->toHaveLength(1);

    $secondMedia = $model->addMedia(
        file: UploadedFile::fake()->image('foo.jpg'),
        disk: 'media',
        collectionName: 'single',
    );

    expect($model->media)->toHaveLength(1);

    Storage::disk('media')->assertExists($secondMedia->path);

    Storage::disk('media')->assertMissing($firstMedia->path);
    expect(Media::query()->find($firstMedia->id))->toBe(null);

});

it('deletes media and its files with the model when delete_media_with_model is true', function () {
    config()->set('media.delete_media_with_model', true);

    Storage::fake('media');

    $model = new Test;
    $model->save();

    $media = $model->addMedia(
        file: UploadedFile::fake()->image('foo.jpg'),
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

    $model = new Test;
    $model->save();

    $file = UploadedFile::fake()->image('foo.jpg');

    $media = $model->addMedia(
        file: $file,
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

    $model = new TestSoftDelete;
    $model->save();

    $file = UploadedFile::fake()->image('foo.jpg');

    $media = $model->addMedia(
        file: $file,
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

    $model = new TestSoftDelete;
    $model->save();

    $file = UploadedFile::fake()->image('foo.jpg');

    $media = $model->addMedia(
        file: $file,
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

    $model = new TestSoftDelete;
    $model->save();

    $file = UploadedFile::fake()->image('foo.jpg');

    $media = $model->addMedia(
        file: $file,
        disk: 'media'
    );

    Storage::disk('media')->assertExists($media->path);

    $model->forceDelete();

    expect($media->fresh())->toBe(null);

    Storage::disk('media')->assertMissing($media->path);
});
