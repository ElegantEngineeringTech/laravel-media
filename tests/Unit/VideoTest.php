<?php

declare(strict_types=1);

use Elegantly\Media\Helpers\Video;

it('get the correct dimension from a non rotated video', function () {

    $file = $this->getTestFile('videos/horizontal.mp4');

    $dimension = Video::dimension($file);

    expect($dimension?->height)->toBe(720);
    expect($dimension?->width)->toBe(1280);
});

it('get the correct dimension from a rotated video', function () {

    $file = $this->getTestFile('videos/90rotation.mp4');

    $dimension = Video::dimension($file);

    expect($dimension?->height)->toBe(1920);
    expect($dimension?->width)->toBe(1080);
});

it('get the correct duration of a video', function () {

    $file = $this->getTestFile('videos/horizontal.mp4');

    $duration = Video::duration($file);

    expect($duration)->toBe(2736.067);
});
