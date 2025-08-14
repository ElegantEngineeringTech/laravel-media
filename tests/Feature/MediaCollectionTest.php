<?php

declare(strict_types=1);

use Elegantly\Media\Converters\Image\MediaImageConverter;
use Elegantly\Media\MediaCollection;
use Elegantly\Media\MediaConversionDefinition;

it('retrieves conversions definitions using dot notation', function () {
    $collection = new MediaCollection(
        name: 'foo',
        conversions: [
            new MediaConversionDefinition(
                name: 'poster',
                converter: fn ($media) => new MediaImageConverter($media, 'foo.jpg'),
                conversions: [
                    new MediaConversionDefinition(
                        name: '360',
                        converter: fn ($media) => new MediaImageConverter($media, 'foo.jpg'),
                        conversions: [],
                    ),
                ],
            ),
        ],
    );

    expect($collection->getConversionDefinition('poster')?->name)->tobe('poster');
    expect($collection->getConversionDefinition('poster.360')?->name)->tobe('360');
});
