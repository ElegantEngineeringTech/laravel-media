<?php

declare(strict_types=1);

use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Tests\Models\TestPdf;
use Illuminate\Support\Facades\Storage;

it('generates a Pdf preview', function () {
    Storage::fake('media');
    $model = new TestPdf;
    $model->save();

    $media = $model->addMedia(
        file: $this->getTestFile('pdfs/dummy.pdf'),
        collectionName: 'files',
        disk: 'media'
    );
    // because some conversions are queued
    $media->refresh();

    $conversion = $media->getConversion('preview');

    expect($conversion)->not->toBe(null);
    expect($conversion->type)->toBe(MediaType::Image);
    expect($conversion->width)->toBe(100);
});
