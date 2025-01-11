<?php

declare(strict_types=1);

use Elegantly\Media\Definitions\MediaConversionDefinition;
use Elegantly\Media\MediaCollection;

it('retreives conversions definitions using dot notation', function () {
    $collection = new MediaCollection(
        name: 'foo',
        conversions: [
            new MediaConversionDefinition(
                name: 'poster',
                handle: fn () => null,
                conversions: [
                    new MediaConversionDefinition(
                        name: '360',
                        handle: fn () => null,
                        conversions: [],
                    ),
                ],
            ),
        ],
    );

    expect($collection->getConversionDefinition('poster')?->name)->tobe('poster');
    expect($collection->getConversionDefinition('poster.360')?->name)->tobe('360');
});
