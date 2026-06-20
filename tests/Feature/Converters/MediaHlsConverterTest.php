<?php

declare(strict_types=1);

use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\Tests\Models\TestVideo;
use Illuminate\Support\Facades\Storage;

it('converts an mp4 video into a hls playlist', function () {
    Storage::fake('media');

    $model = new TestVideo;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('videos/horizontal.mp4'),
        collectionName: 'hls',
        disk: 'media',
    );

    $conversion = $media->executeConversion('hls');

    expect($conversion)->toBeInstanceOf(MediaConversion::class);
    expect($conversion->name)->toBe('master');
    expect($conversion->extension)->toBe('m3u8');
    expect($conversion->mime_type)->toBe('application/vnd.apple.mpegurl');
    expect($conversion->type)->toBe(MediaType::Video);

    expect($conversion->additional_files)->toHaveLength(4 * 2);

});
