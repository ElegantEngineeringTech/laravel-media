<?php

use Finller\Media\Helpers\Video;

it('get the correct dimention from a non rotated video', function () {

    $file = $this->getTestFile('videos/horizontal.mp4');

    $dimension = Video::dimension($file);

    expect($dimension->getHeight())->toBe(720);
    expect($dimension->getWidth())->toBe(1280);
});

it('get the correct dimention from a rotated video', function () {

    $file = $this->getTestFile('videos/90rotation.mp4');

    $dimension = Video::dimension($file);

    expect($dimension->getHeight())->toBe(1920);
    expect($dimension->getWidth())->toBe(1080);
});
