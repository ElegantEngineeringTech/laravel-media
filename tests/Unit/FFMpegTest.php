<?php

declare(strict_types=1);

use Elegantly\Media\FFMpeg\FFMpeg;

it('detects audio and video stream in a mp4.', function () {

    $file = $this->getTestFile('videos/horizontal.mp4');

    $hasVideo = FFMpeg::make()->hasVideo($file);
    $hasAudio = FFMpeg::make()->hasAudio($file);
    $hasArtwork = FFMpeg::make()->hasArtwork($file);

    expect($hasVideo)->toBe(true);
    expect($hasAudio)->toBe(true);
    expect($hasArtwork)->toBe(false);

});

it('detects audio and video stream in a webm.', function () {

    $file = $this->getTestFile('videos/horizontal.webm');

    $hasVideo = FFMpeg::make()->hasVideo($file);
    $hasAudio = FFMpeg::make()->hasAudio($file);
    $hasArtwork = FFMpeg::make()->hasArtwork($file);

    expect($hasVideo)->toBe(true);
    expect($hasAudio)->toBe(true);
    expect($hasArtwork)->toBe(false);

});

it('detects audio stream in a mp3.', function () {

    $file = $this->getTestFile('audios/bipbip.mp3');

    $hasVideo = FFMpeg::make()->hasVideo($file);
    $hasAudio = FFMpeg::make()->hasAudio($file);
    $hasArtwork = FFMpeg::make()->hasArtwork($file);

    expect($hasVideo)->toBe(false);
    expect($hasAudio)->toBe(true);
    expect($hasArtwork)->toBe(false);

});

it('detects artwork stream in an mp3.', function () {

    $file = $this->getTestFile('audios/artwork.mp3');

    $hasVideo = FFMpeg::make()->hasVideo($file);
    $hasAudio = FFMpeg::make()->hasAudio($file);
    $hasArtwork = FFMpeg::make()->hasArtwork($file);

    expect($hasVideo)->toBe(false);
    expect($hasAudio)->toBe(true);
    expect($hasArtwork)->toBe(true);

});
