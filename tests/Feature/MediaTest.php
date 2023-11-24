<?php

use Finller\LaravelMedia\Casts\GeneratedConversion;
use Finller\LaravelMedia\Database\Factories\MediaFactory;
use Finller\LaravelMedia\Enums\GeneratedConversionState;
use Finller\LaravelMedia\Enums\MediaType;
use Finller\LaravelMedia\Media;

$generated_conversions = [
    'poster' => [
        'state' => GeneratedConversionState::Success,
        'file_name' => 'poster.png',
        'path' => '/poster/poster.png',
        'conversions' => [
            '480p' => [
                'state' => GeneratedConversionState::Success,
                'file_name' => 'poster-480p.png',
                'path' => '/poster/conversions/480p/poster-480p.png',
            ],
        ],
    ],
];

it('retrieve the correct generated conversion key', function () {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    expect($media->getConversionKey('poster'))->toBe('poster');
    expect($media->getConversionKey('poster.480p'))->toBe('poster.conversions.480p');
    expect($media->getConversionKey('poster.square.480p'))->toBe('poster.conversions.square.conversions.480p');
});

it('retrieve the correct generated conversion', function () use ($generated_conversions) {
    /** @var Media $media */
    $media = MediaFactory::new()->make();

    $media->generated_conversions = $generated_conversions;

    expect($media->hasGeneratedConversion('poster'))->toBe(true);
    expect($media->hasGeneratedConversion('poster.480p'))->toBe(true);
    expect($media->hasGeneratedConversion('poster.480p.foo'))->toBe(false);

    expect($media->getGeneratedConversion('poster'))->toBeInstanceof(GeneratedConversion::class);
    expect($media->getGeneratedConversion('poster.480p'))->toBeInstanceof(GeneratedConversion::class);
    expect($media->getGeneratedConversion('poster.480p.foo'))->toBe(null);
});

it('retrieve the correct generated conversion path', function () use ($generated_conversions) {

    /** @var Media $media */
    $media = MediaFactory::new()->make();

    $media->generated_conversions = $generated_conversions;

    expect($media->getPath('poster'))->toBe('/poster/poster.png');
    expect($media->getPath('poster.480p'))->toBe('/poster/conversions/480p/poster-480p.png');
});


it('add the correct generated conversion', function () use ($generated_conversions) {

    /** @var Media $media */
    $media = MediaFactory::new()->make();

    $media->generated_conversions = $generated_conversions;

    $media->addGeneratedConversion('optimized', new GeneratedConversion(
        file_name: 'optimized.png',
        state: GeneratedConversionState::Pending,
        path: '/optimized/optimized.png',
        type: MediaType::Image,
    ));

    $media->addGeneratedConversion('poster-optimized', new GeneratedConversion(
        file_name: 'poster-optimized.png',
        state: GeneratedConversionState::Pending,
        path: 'poster/conversions/optimized/poster-optimized.png',
        type: MediaType::Image,
    ), 'poster');

    expect($media->hasGeneratedConversion('optimized'))->toBe(true);
    expect($media->hasGeneratedConversion('poster.poster-optimized'))->toBe(true);
});
