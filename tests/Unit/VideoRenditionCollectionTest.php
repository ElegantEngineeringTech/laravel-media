<?php

declare(strict_types=1);

use Elegantly\Media\Helpers\Bitrate;
use Elegantly\Media\MediaServiceProvider;

it('cap the maxrate and bufsize to the max value', function ($max) {
    $max = Bitrate::parse($max);

    $renditions = MediaServiceProvider::getVideoRenditions()
        ->setMaxBitrate($max);

    foreach ($renditions as $rendition) {
        expect($rendition->maxrate->value)->toBeLessThanOrEqual($max->value);
        expect($rendition->bufsize->value)->toBeLessThanOrEqual($max->value * 1.5);
    }

})->with([
    ['1k'], ['600k'], ['5000k'],
]);

it('cap the audioBitrate to the max value', function ($max) {
    $max = Bitrate::parse($max);

    $renditions = MediaServiceProvider::getVideoRenditions()
        ->setMaxAudioBitrate($max);

    foreach ($renditions as $rendition) {
        expect($rendition->audioBitrate->value)->toBeLessThanOrEqual($max->value);
    }

})->with([
    ['1k'], ['96k'], ['192k'],
]);

it('computes the 16:9 dimension of a rendition', function (int $size, array $landscape, array $portrait) {
    $rendition = MediaServiceProvider::getVideoRenditions()->firstWhere('size', $size);

    $dimension = $rendition->dimension();
    expect([$dimension->width, $dimension->height])->toBe($landscape);

    $dimension = $rendition->dimension(portrait: true);
    expect([$dimension->width, $dimension->height])->toBe($portrait);
})->with([
    [2160, [3840, 2160], [2160, 3840]],
    [1080, [1920, 1080], [1080, 1920]],
    [480, [854, 480], [480, 854]],
    [240, [426, 240], [240, 426]],
]);
