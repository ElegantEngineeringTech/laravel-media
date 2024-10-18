<?php

use Elegantly\Media\Models\Media;

it('retreives the size in a human readable format', function () {

    $media = new Media([
        'size' => 12345,
    ]);

    expect($media->humanReadableSize())->tobe('12 KB');

});

it('retreives the duration in a human readable format', function () {

    $media = new Media([
        'duration' => 123456.00,
    ]);

    expect($media->duration)->toBeFloat();

    expect($media->humanReadableDuration())->tobe('2 minutes 3 seconds');

});
