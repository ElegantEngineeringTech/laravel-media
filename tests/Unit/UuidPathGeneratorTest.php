<?php

declare(strict_types=1);

use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\PathGenerators\UuidPathGenerator;

it('generates a media path', function ($prefix, $uuid, $fileName, $path) {

    $generator = new UuidPathGenerator($prefix);

    $media = new Media(['uuid' => $uuid]);

    $path = $generator->generate(
        media: $media,
        mediaConversion: null,
        fileName: $fileName
    );

    expect($path)->toBe($path);

})->with([
    [null, '1d0b3eff-f998-41c3-8b20-095ffd2ecdd4', 'foo.png', '1d0b3eff-f998-41c3-8b20-095ffd2ecdd4/foo.png'],
    ['media', '1d0b3eff-f998-41c3-8b20-095ffd2ecdd4', 'foo.png', 'media/1d0b3eff-f998-41c3-8b20-095ffd2ecdd4/foo.png'],
    ['media/', '1d0b3eff-f998-41c3-8b20-095ffd2ecdd4', 'foo.png', 'media/1d0b3eff-f998-41c3-8b20-095ffd2ecdd4/foo.png'],
]);

it('generates a media conversion path', function (
    $prefix,
    $media,
    $mediaConversion,
    $fileName,
    $path
) {

    $generator = new UuidPathGenerator($prefix);

    $media = new Media($media);

    $mediaConversion = new MediaConversion($mediaConversion);

    $path = $generator->generate(
        media: $media,
        mediaConversion: $mediaConversion,
        fileName: $fileName
    );

    expect($path)->toBe($path);

})->with([
    [
        null,
        ['uuid' => '1d0b3eff-f998-41c3-8b20-095ffd2ecdd4'],
        ['uuid' => 'a67c9576-075a-4093-b7ae-babb68069183', 'conversion_name' => 'poster'],
        'foo.png',
        '1d0b3eff-f998-41c3-8b20-095ffd2ecdd4/conversions/poster/a67c9576-075a-4093-b7ae-babb68069183/foo.png',
    ],
    [
        'media',
        ['uuid' => '1d0b3eff-f998-41c3-8b20-095ffd2ecdd4'],
        ['uuid' => 'a67c9576-075a-4093-b7ae-babb68069183', 'conversion_name' => 'poster'],
        'foo.png',
        'media/1d0b3eff-f998-41c3-8b20-095ffd2ecdd4/conversions/poster/a67c9576-075a-4093-b7ae-babb68069183/foo.png',
    ],
    [
        'media/',
        ['uuid' => '1d0b3eff-f998-41c3-8b20-095ffd2ecdd4'],
        ['uuid' => 'a67c9576-075a-4093-b7ae-babb68069183', 'conversion_name' => 'poster'],
        'foo.png',
        'media/1d0b3eff-f998-41c3-8b20-095ffd2ecdd4/conversions/poster/a67c9576-075a-4093-b7ae-babb68069183/foo.png',
    ],
    [
        'media',
        ['uuid' => '1d0b3eff-f998-41c3-8b20-095ffd2ecdd4'],
        ['uuid' => 'a67c9576-075a-4093-b7ae-babb68069183', 'conversion_name' => 'poster.360'],
        'foo.png',
        'media/1d0b3eff-f998-41c3-8b20-095ffd2ecdd4/conversions/poster/conversions/360/a67c9576-075a-4093-b7ae-babb68069183/foo.png',
    ],
]);
