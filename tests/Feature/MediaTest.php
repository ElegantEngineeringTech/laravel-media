<?php

use Elegantly\Media\Casts\GeneratedConversion;
use Elegantly\Media\Database\Factories\MediaFactory;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Models\Media;
use FFMpeg\Coordinate\Dimension;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('retrieve the url', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    expect($media->getUrl())->toBe('/storage//uuid/empty.jpg');
    expect($media->getUrl(
        parameters: [
            'width' => 200,
            'height' => 200,
        ]
    ))->toBe('/storage//uuid/empty.jpg?width=200&height=200');

});

it('retrieve the fallback url', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    expect($media->getUrl(
        conversion: 'poster',
        fallback: true,
    ))->toBe('/storage//uuid/empty.jpg');

    expect($media->getUrl(
        conversion: 'poster',
        fallback: true,
        parameters: [
            'width' => 200,
            'height' => 200,
        ]
    ))->toBe('/storage//uuid/empty.jpg?width=200&height=200');

});

it('retrieve the conversion url', function () {

    /** @var Media $media */
    $media = MediaFactory::new()->withPoster()->make();

    expect($media->getUrl(
        conversion: 'poster',
        fallback: true,
    ))->toBe('/storage//uuid/poster/poster.png');

    expect($media->getUrl(
        conversion: 'poster',
        fallback: true,
        parameters: [
            'width' => 200,
            'height' => 200,
        ]
    ))->toBe('/storage//uuid/poster/poster.png?width=200&height=200');

});

it('retrieve the generated conversion key', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    expect($media->makeGeneratedConversionKey('poster'))->toBe('poster');
    expect($media->makeGeneratedConversionKey('poster.480p'))->toBe('poster.generated_conversions.480p');
    expect($media->makeGeneratedConversionKey('poster.square.480p'))->toBe('poster.generated_conversions.square.generated_conversions.480p');
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
    expect($media->getPath('poster.480p'))->toBe('/poster/generated_conversions/480p/poster-480p.png');
});

it('retrieve the generated conversion url', function () {
    Storage::fake('media');

    /** @var Media $media */
    $media = MediaFactory::new()->make();

    $media->generated_conversions = collect([
        'poster' => MediaFactory::generatedConversion(disk: 'media'),
    ]);

    expect($media->getUrl('poster'))->toBe('/storage//poster/poster.png');
    expect($media->getUrl('poster.480p'))->toBe('/storage//poster/generated_conversions/480p/poster-480p.png');
});

it('retrieve the generated conversion url fallback', function () {
    Storage::fake('media');

    /** @var Media $media */
    $media = MediaFactory::new()->withPoster()->make([
        'path' => '/uuid/empty.jpg',
    ]);

    expect($media->getUrl(
        conversion: 'missing',
        fallback: true
    ))->toBe('/storage//uuid/empty.jpg');

    expect($media->getUrl(
        conversion: 'missing',
        fallback: 'poster'
    ))->toBe('/storage//uuid/poster/poster.png');

    expect($media->getUrl(
        conversion: 'missing',
        fallback: 'foo'
    ))->toBe('foo');

    expect($media->getUrl(
        conversion: 'missing'
    ))->toBe(null);
});

it('add the generated conversion', function () {

    /** @var Media $media */
    $media = MediaFactory::new()->make();

    $media->generated_conversions = collect([
        'poster' => MediaFactory::generatedConversion(),
    ]);

    $media->putGeneratedConversion('optimized', new GeneratedConversion(
        file_name: 'optimized.png',
        name: 'optimized',
        state: 'pending',
        path: '/optimized/optimized.png',
        type: MediaType::Image,
        disk: config('media.disk')
    ));

    $media->putGeneratedConversion('poster.poster-optimized', new GeneratedConversion(
        file_name: 'poster-optimized.png',
        name: 'poster-optimized',
        state: 'pending',
        path: 'poster/generated_conversions/optimized/poster-optimized.png',
        type: MediaType::Image,
        disk: config('media.disk')
    ));

    expect($media->hasGeneratedConversion('optimized'))->toBe(true);
    expect($media->hasGeneratedConversion('poster.poster-optimized'))->toBe(true);
});

it('update a generated conversion', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    $media
        ->putGeneratedConversion('poster', new GeneratedConversion(
            file_name: 'poster.png',
            name: 'poster',
            state: 'success',
            path: '/optimized/poster.png',
            type: MediaType::Image,
            disk: config('media.disk')
        ))
        ->save();

    $generatedConversion = $media->getGeneratedConversion('poster');

    expect($generatedConversion->state)->toBe('success');

    $generatedConversion->state = 'failed';
    $media->save();

    $media->refresh();

    expect($generatedConversion->state)->tobe('failed');
});

it('store an uploaded image', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media');

    $file = UploadedFile::fake()->image('foo.jpg', width: 16, height: 9);

    $media->storeFile(
        file: $file,
        disk: 'media'
    );

    expect($media->name)->toBe('foo');
    expect($media->file_name)->toBe('foo.jpg');
    expect($media->path)->toBe("{$media->uuid}/foo.jpg");

    Storage::disk('media')->assertExists($media->path);
});

it('store an uploaded image with a custom name', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media');

    $file = UploadedFile::fake()->image('foo.jpg', width: 16, height: 9);

    $media->storeFile(
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
    expect($media->path)->toBe("{$media->uuid}/avatar.jpg");

    Storage::disk('media')->assertExists($media->path);
});

it('store a pdf from an url with a custom name', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media');

    $media->storeFile(
        file: $this->dummy_pdf_url,
        disk: 'media',
        name: 'foo'
    );

    expect($media->name)->toBe('foo');
    expect($media->file_name)->toBe('foo.pdf');
    expect($media->path)->toBe("{$media->uuid}/foo.pdf");

    Storage::disk('media')->assertExists($media->path);
});

it('store a svg file', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media');

    $file = $this->getTestFile('images/svg.svg');

    $media->storeFile(
        file: $file,
        disk: 'media',
        name: 'foo'
    );

    expect($media->name)->toBe('foo');
    expect($media->file_name)->toBe('foo.svg');
    expect($media->path)->toBe("{$media->uuid}/foo.svg");

    Storage::disk('media')->assertExists($media->path);
});

it('store a file within a prefixed path', function () {
    config()->set('media.generated_path_prefix', 'media');

    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media');

    $file = $this->getTestFile('images/svg.svg');

    $media->storeFile(
        file: $file,
        disk: 'media',
        name: 'foo'
    );

    expect($media->name)->toBe('foo');
    expect($media->file_name)->toBe('foo.svg');
    expect($media->path)->toBe("media/{$media->uuid}/foo.svg");

    Storage::disk('media')->assertExists($media->path);
});

it('limit the name length to 255', function () {
    config()->set('media.generated_path_prefix', 'media');

    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media');

    $file = $this->getTestFile('images/svg.svg');

    $media->storeFile(
        file: $file,
        disk: 'media',
        name: 'aaaaaaaa10aaaaaaaa20aaaaaaaa30aaaaaaaa40aaaaaaaa50aaaaaaaa60aaaaaaaa70aaaaaaaa80aaaaaaaa90aaaaaaaa10aaaaaaaa20aaaaaaaa30aaaaaaaa40aaaaaaaa40aaaaaaaa50aaaaaaaa60aaaaaaaa70aaaaaaaa80aaaaaaaa90aaaaaaaa10aaaaaaaa20aaaaaaaa30aaaaaaaa40aaaaaaaa50aaaaaaaa60aaaaa',
    );

    expect($media->name)->toBe('aaaaaaaa10aaaaaaaa20aaaaaaaa30aaaaaaaa40aaaaaaaa50aaaaaaaa60aaaaaaaa70aaaaaaaa80aaaaaaaa90aaaaaaaa10aaaaaaaa20aaaaaaaa30aaaaaaaa40aaaaaaaa40aaaaaaaa50aaaaaaaa60aaaaaaaa70aaaaaaaa80aaaaaaaa90aaaaaaaa10aaaaaaaa');
    expect($media->file_name)->toBe('aaaaaaaa10aaaaaaaa20aaaaaaaa30aaaaaaaa40aaaaaaaa50aaaaaaaa60aaaaaaaa70aaaaaaaa80aaaaaaaa90aaaaaaaa10aaaaaaaa20aaaaaaaa30aaaaaaaa40aaaaaaaa40aaaaaaaa50aaaaaaaa60aaaaaaaa70aaaaaaaa80aaaaaaaa90aaaaaaaa10aaaaaaaa.svg');
    expect($media->path)->toBe("media/{$media->uuid}/aaaaaaaa10aaaaaaaa20aaaaaaaa30aaaaaaaa40aaaaaaaa50aaaaaaaa60aaaaaaaa70aaaaaaaa80aaaaaaaa90aaaaaaaa10aaaaaaaa20aaaaaaaa30aaaaaaaa40aaaaaaaa40aaaaaaaa50aaaaaaaa60aaaaaaaa70aaaaaaaa80aaaaaaaa90aaaaaaaa10aaaaaaaa.svg");

    Storage::disk('media')->assertExists($media->path);
});

it('store a conversion image of a media', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media');

    $orginial = UploadedFile::fake()->image('foo.jpg', width: 16, height: 9);

    $media->storeFile(
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
    expect($generatedConversion->path)->toBe("{$media->uuid}/generated_conversions/poster/avatar-poster.jpg");
    expect($generatedConversion->path)->toBe($media->getPath('poster'));

    Storage::disk('media')->assertExists($generatedConversion->path);
});

it('store a conversion image of a conversion', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media');

    $media->storeFile(
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
    expect($generatedConversion->path)->toBe("{$media->uuid}/generated_conversions/poster/generated_conversions/small/avatar-poster-small.jpg");
    expect($generatedConversion->path)->toBe($media->getPath('poster.small'));

    Storage::disk('media')->assertExists($generatedConversion->path);
});

it('delete a media generated conversion with its own conversions', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media');

    $media->storeFile(
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

    $generatedConversion = $media->getGeneratedConversion('poster');
    $nestedGeneratedConversion = $media->getGeneratedConversion('poster.small');

    Storage::disk('media')->assertExists($generatedConversion->path);
    Storage::disk('media')->assertExists($nestedGeneratedConversion->path);

    $media->deleteGeneratedConversion('poster');

    expect($media->getGeneratedConversion('poster'))->toBe(null);

    Storage::disk('media')->assertMissing($generatedConversion->path);
    Storage::disk('media')->assertMissing($nestedGeneratedConversion->path);
});

it('delete all files when model deleted', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    Storage::fake('media');

    $media->storeFile(
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

    $generatedConversion = $media->getGeneratedConversion('poster');
    $nestedGeneratedConversion = $media->getGeneratedConversion('poster.small');

    Storage::disk('media')->assertExists($media->path);
    Storage::disk('media')->assertExists($generatedConversion->path);
    Storage::disk('media')->assertExists($nestedGeneratedConversion->path);

    $media->delete();

    Storage::disk('media')->assertMissing($media->path);
    Storage::disk('media')->assertMissing($generatedConversion->path);
    Storage::disk('media')->assertMissing($nestedGeneratedConversion->path);
});

it('reorder models', function () {

    $first_media = MediaFactory::new()->create(['order_column' => 0]);
    $second_media = MediaFactory::new()->create(['order_column' => 1]);
    $third_media = MediaFactory::new()->create(['order_column' => 2]);

    Media::reorder([
        $third_media->getKey(),
        $first_media->getKey(),
        $second_media->getKey(),
    ]);

    expect($third_media->refresh()->order_column)->toBe(0);
    expect($first_media->refresh()->order_column)->toBe(1);
    expect($second_media->refresh()->order_column)->toBe(2);
});

it('reorder models using uuids', function () {

    $first_media = MediaFactory::new()->create(['order_column' => 0]);
    $second_media = MediaFactory::new()->create(['order_column' => 1]);
    $third_media = MediaFactory::new()->create(['order_column' => 2]);

    Media::reorder([
        $third_media->uuid,
        $first_media->uuid,
        $second_media->uuid,
    ], using: 'uuid');

    expect($third_media->refresh()->order_column)->toBe(0);
    expect($first_media->refresh()->order_column)->toBe(1);
    expect($second_media->refresh()->order_column)->toBe(2);
});

it('reorder models from a custom sequence', function () {

    $first_media = MediaFactory::new()->create(['order_column' => 0]);
    $second_media = MediaFactory::new()->create(['order_column' => 1]);
    $third_media = MediaFactory::new()->create(['order_column' => 2]);

    Media::reorder([
        $third_media->getKey(),
        $first_media->getKey(),
        $second_media->getKey(),
    ], sequence: fn (?int $previous) => ($previous === null ? 0 : ($previous + 2)));

    expect($third_media->refresh()->order_column)->toBe(0);
    expect($first_media->refresh()->order_column)->toBe(2);
    expect($second_media->refresh()->order_column)->toBe(4);
});
