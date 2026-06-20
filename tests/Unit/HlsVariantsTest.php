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
