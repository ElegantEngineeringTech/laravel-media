<?php

namespace Finller\LaravelMedia;

use Finller\LaravelMedia\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property ?string $uuid
 * @property string $disk
 * @property string $path
 * @property string $type
 * @property string $name
 * @property string $file_name
 * @property int $size
 * @property ?string $mime_type
 * @property ?string $extension
 * @property ?string $collection
 * @property ?int $width
 * @property ?int $height
 * @property ?string $aspect_ratio
 * @property ?string $orientation
 * @property ?string $average_color
 * @property ?int $order_column
 * @property ?ArrayObject $generated_conversions
 * @property ?ArrayObject $metadata
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

    function getUrl()
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
