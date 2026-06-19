<?php

declare(strict_types=1);

use Elegantly\Media\FFMpeg\FFMpeg;
use Elegantly\Media\FFMpeg\Video;
use Elegantly\Media\TemporaryDirectory;

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

it('generates m3u8 hls renditions supported by the source resolution', function () {
    $file = $this->getTestFile('videos/horizontal.mp4');

    $temporaryDirectory = (new TemporaryDirectory)
        ->location(storage_path('media-tmp'))
        ->deleteWhenDestroyed()
        ->create();

    $filesystem = TemporaryDirectory::storage($temporaryDirectory);

    $output = $temporaryDirectory->path();
    $playlist = 'master.m3u8';

    Video::make()->hls($file, $output, $playlist);

    $filesystem->assertExists($playlist);
    $filesystem->assertExists('720p_segment_00000.ts');
    $filesystem->assertExists('720p_playlist.m3u8');
    $filesystem->assertExists('480p_segment_00000.ts');
    $filesystem->assertExists('480p_playlist.m3u8');
    $filesystem->assertExists('360p_segment_00000.ts');
    $filesystem->assertExists('360p_playlist.m3u8');
});
