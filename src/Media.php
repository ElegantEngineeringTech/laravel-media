<?php

namespace Finller\LaravelMedia;

use Finller\LaravelMedia\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @var int $id
 * @var string $uuid
 * @var string $disk
 * @var string $path
 * @var string $type
 * @var string $name
 * @var string $file_name
 * @var int $size
 * @var ?string $mime
 * @var ?string $extension
 * @var ?string $collection
 * @var ?int $width
 * @var ?int $height
 * @var ?string $aspect_ratio
 * @var ?string $orientation
 * @var ?string $average_color
 * @var ?int $order
 * @var ?ArrayObject $conversions
 * @var ?ArrayObject $metadata
 */
class Media extends Model
{
    use HasUuid;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'disk',
        'path',
        'type',
        'name',
        'file_name',
        'size',
        'mime',
        'extension',
        'collection',
        'width',
        'height',
        'aspect_ratio',
        'orientation',
        'average_color',
        'order',
        'conversions',
        'metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => AsArrayObject::class,
        'conversions' => AsArrayObject::class,
    ];

    function model(): MorphTo
    {
        return $this->morphTo();
    }
}
