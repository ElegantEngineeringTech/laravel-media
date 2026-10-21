<?php

declare(strict_types=1);

use Elegantly\Media\Helpers\Bitrate;
use Elegantly\Media\Helpers\HlsVariants;

it('cap the bitrate, maxrate and buffsize to the max value', function ($max) {
    $max = Bitrate::parse($max);

    $variants = HlsVariants::defaults()
        ->setMaxBitrate($max);

    foreach ($variants as $variant) {
        expect($variant->bitrate->value)->toBeLessThanOrEqual($max->value);
        expect($variant->maxrate->value)->toBeLessThanOrEqual($max->value);
        expect($variant->bufsize->value)->toBeLessThanOrEqual($max->value * 1.5);
    }

})->with([
    ['1k'], ['600k'], ['5000k'],
]);

it('cap the audioBitrate to the max value', function ($max) {
    $max = Bitrate::parse($max);

    $variants = HlsVariants::defaults()
        ->setMaxAudioBitrate($max);

    foreach ($variants as $variant) {
        expect($variant->audioBitrate->value)->toBeLessThanOrEqual($max->value);
    }

})->with([
    ['1k'], ['96k'], ['192k'],
]);

it('computes the max 16:9 dimension of a variant', function (string $name, array $landscape, array $portrait) {
    $variant = HlsVariants::defaults()->firstWhere('name', $name);

    $dimension = $variant->maxDimension();
    expect([$dimension->width, $dimension->height])->toBe($landscape);

    $dimension = $variant->maxDimension(portrait: true);
    expect([$dimension->width, $dimension->height])->toBe($portrait);
})->with([
    ['2160p', [3840, 2160], [2160, 3840]],
    ['1080p', [1920, 1080], [1080, 1920]],
    ['480p', [854, 480], [480, 854]],
    ['240p', [426, 240], [240, 426]],
]);
