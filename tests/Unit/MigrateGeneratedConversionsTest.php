<?php

declare(strict_types=1);

it('transform an array of generated conversions into MediaConversion array', function () {

    $generated_conversions = [
        null,
        [],
        '',
        'foo' => [
            'state' => 'succeeded',
            'state_set_at' => now()->toJSON(),
            'disk' => 's3',
            'path' => 'uuid/foo.jpg',
            'type' => 'image',
            'name' => 'foo',
            'extension' => 'jpg',
            'file_name' => 'foo.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 16,
            'height' => 9,
            'aspect_ratio' => 16 / 9,
            'average_color' => null,
            'size' => 100,
            'duration' => null,
            'metadata' => [],
            'created_at' => now()->toJSON(),
            'updated_at' => now()->toJSON(),
            'generated_conversions' => [
                null,
                [],
                '',
                'nested' => [
                    'state' => 'succeeded',
                    'state_set_at' => now()->toJSON(),
                    'disk' => 's3',
                    'path' => 'uuid/nested.jpg',
                    'type' => 'image',
                    'name' => 'nested',
                    'extension' => 'jpg',
                    'file_name' => 'nested.jpg',
                    'mime_type' => 'image/jpeg',
                    'width' => 16,
                    'height' => 9,
                    'aspect_ratio' => 16 / 9,
                    'average_color' => null,
                    'size' => 100,
                    'duration' => null,
                    'metadata' => [],
                    'created_at' => now()->toJSON(),
                    'updated_at' => now()->toJSON(),
                    'generated_conversions' => [
                        'supernested' => [
                            'state' => 'succeeded',
                            'state_set_at' => now()->toJSON(),
                            'disk' => 's3',
                            'path' => 'uuid/supernested.jpg',
                            'type' => 'image',
                            'name' => 'supernested',
                            'extension' => 'jpg',
                            'file_name' => 'supernested.jpg',
                            'mime_type' => 'image/jpeg',
                            'width' => 16,
                            'height' => 9,
                            'aspect_ratio' => 16 / 9,
                            'average_color' => null,
                            'size' => 100,
                            'duration' => null,
                            'metadata' => [],
                            'created_at' => now()->toJSON(),
                            'updated_at' => now()->toJSON(),
                        ],
                    ],
                ],
            ],
        ],
        'bar' => [
            'state' => 'succeeded',
            'state_set_at' => now()->toJSON(),
            'disk' => 's3',
            'path' => 'uuid/bar.jpg',
            'type' => 'image',
            'name' => 'bar',
            'extension' => 'jpg',
            'file_name' => 'bar.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 16,
            'height' => 9,
            'aspect_ratio' => 16 / 9,
            'average_color' => null,
            'size' => 100,
            'duration' => null,
            'metadata' => [],
            'created_at' => now()->toJSON(),
            'updated_at' => now()->toJSON(),
        ],
    ];

    $migration = include __DIR__.'/../../database/migrations/migrate_generated_conversions_to_media_conversions_table.php.stub';

    $conversions = collect($migration->generatedConversionsToMediaConversions($generated_conversions));

    expect($conversions)->toHaveLength(4);
    expect($conversions->firstWhere('conversion_name', 'foo'))->not->toBe(null);
    expect($conversions->firstWhere('conversion_name', 'foo.nested'))->not->toBe(null);
    expect($conversions->firstWhere('conversion_name', 'foo.nested.supernested'))->not->toBe(null);
    expect($conversions->firstWhere('conversion_name', 'bar'))->not->toBe(null);

});
