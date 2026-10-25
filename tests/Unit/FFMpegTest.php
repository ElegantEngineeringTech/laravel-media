<?php

declare(strict_types=1);

use Elegantly\Media\FFMpeg\FFMpeg;
use Elegantly\Media\FFMpeg\Video;
use Elegantly\Media\Helpers\VideoRendition;
use Elegantly\Media\Helpers\VideoRenditionCollection;
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

    $filesystem = $temporaryDirectory->toFilesystem();

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
    $filesystem->assertExists('240p_segment_00000.ts');
    $filesystem->assertExists('240p_playlist.m3u8');

    // A single audio rendition at the default 128k, capped to the source's 72.5 kb/s
    $filesystem->assertExists('audio_72k_segment_00000.ts');
    $filesystem->assertExists('audio_72k_playlist.m3u8');

    $filesystem->assertMissing('1080p_playlist.m3u8');
    $filesystem->assertMissing('1440p_playlist.m3u8');
    $filesystem->assertMissing('2160p_playlist.m3u8');

});

it('generates m3u8 hls renditions for a portrait video', function () {
    $file = $this->getTestFile('videos/90rotation.mp4');

    $temporaryDirectory = (new TemporaryDirectory)
        ->location(storage_path('media-tmp'))
        ->deleteWhenDestroyed()
        ->create();

    $filesystem = $temporaryDirectory->toFilesystem();

    $output = $temporaryDirectory->path();
    $playlist = 'master.m3u8';

    Video::make()->hls($file, $output, $playlist);

    $filesystem->assertMissing('1440p_playlist.m3u8');
    $filesystem->assertMissing('2160p_playlist.m3u8');

    $filesystem->assertExists('1080p_playlist.m3u8');
    $filesystem->assertExists('720p_playlist.m3u8');
    $filesystem->assertExists('240p_playlist.m3u8');

    expect(
        Video::make()
            ->dimension($filesystem->path('1080p_segment_00000.ts'))
            ->toArray()
    )->toBe([
        'width' => 1080,
        'height' => 1920,
    ]);

    expect(
        Video::make()
            ->dimension($filesystem->path('720p_segment_00000.ts'))
            ->toArray()
    )->toBe([
        'width' => 720,
        'height' => 1280,
    ]);

});

it('generates one audio rendition per distinct rendition audio bitrate', function () {
    $file = $this->getTestFile('videos/horizontal.mp4');

    $temporaryDirectory = (new TemporaryDirectory)
        ->location(storage_path('media-tmp'))
        ->deleteWhenDestroyed()
        ->create();

    $filesystem = $temporaryDirectory->toFilesystem();

    $output = $temporaryDirectory->path();
    $playlist = 'master.m3u8';

    $renditions = new VideoRenditionCollection([
        new VideoRendition(['size' => 720, 'maxrate' => '2996k', 'bufsize' => '4200k', 'audioBitrate' => '64k']),
        new VideoRendition(['size' => 480, 'maxrate' => '1498k', 'bufsize' => '2100k', 'audioBitrate' => '64k']),
        new VideoRendition(['size' => 240, 'maxrate' => '642k', 'bufsize' => '900k', 'audioBitrate' => '48k']),
    ]);

    Video::make()->hls($file, $output, $playlist, renditions: $renditions);

    $filesystem->assertExists('audio_64k_playlist.m3u8');
    $filesystem->assertExists('audio_48k_playlist.m3u8');

});
