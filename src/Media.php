<?php

namespace Finller\LaravelMedia;

use Finller\LaravelMedia\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @var int $id
 * @var ?string $uuid
 * @var string $disk
 * @var string $path
 * @var string $type
 * @var string $name
 * @var string $file_name
 * @var int $size
 * @var ?string $mime_type
 * @var ?string $extension
 * @var ?string $collection
 * @var ?int $width
 * @var ?int $height
 * @var ?string $aspect_ratio
 * @var ?string $orientation
 * @var ?string $average_color
 * @var ?int $order_column
 * @var ?ArrayObject $generated_conversions
 * @var ?ArrayObject $metadata
 */
class Media extends Model
{
    use HasUuid;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => AsArrayObject::class,
        'generated_conversions' => AsArrayObject::class,
    ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
