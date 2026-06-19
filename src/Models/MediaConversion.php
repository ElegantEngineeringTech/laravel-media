<?php

declare(strict_types=1);

namespace Elegantly\Media\Models;

use Carbon\Carbon;
use Elegantly\Media\Concerns\HasAdditionalFiles;
use Elegantly\Media\Concerns\HasFile;
use Elegantly\Media\Database\Factories\MediaConversionFactory;
use Elegantly\Media\Enums\MediaConversionState;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Traits\HasUuid;
use Elegantly\Media\ValueObjects\AdditionalFile;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property string $conversion_name
 * @property ?MediaConversionState $state
 * @property ?Carbon $state_set_at
 * @property ?string $disk
 * @property ?string $path
 * @property ?MediaType $type
 * @property ?string $name
 * @property ?string $extension
 * @property ?string $file_name
 * @property ?string $mime_type
 * @property ?int $width
 * @property ?int $height
 * @property ?float $aspect_ratio
 * @property ?string $average_color
 * @property ?int $size
 * @property ?float $duration
 * @property ?string $contents Arbitrary stored value
 * @property ?array<array-key, mixed> $metadata
 * @property int $media_id
 * @property Media $media
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ?string $url
 */
class MediaConversion extends Model
{
    use HasAdditionalFiles;

    /** @use HasFactory<MediaConversionFactory>  */
    use HasFactory;

    use HasFile;
    use HasUuid;

    /**
     * @var array<int, string>
     */
    protected $guarded = ['id', 'uuid'];

    protected $appends = ['url'];

    public function casts()
    {
        return [
            'type' => MediaType::class,
            'metadata' => 'array',
            'duration' => 'float',
            'aspect_ratio' => 'float',
            'state' => MediaConversionState::class,
            'state_set_at' => 'datetime',
            'additional_files' => AsCollection::of(AdditionalFile::class),
        ];
    }

    public static function booted()
    {
        static::creating(function (MediaConversion $conversion) {
            $conversion->state_set_at ??= now();
        });

        static::updating(function (MediaConversion $conversion) {
            if ($conversion->isDirty('state_set_at')) {
                $conversion->state_set_at = now();
            }
        });

        static::deleting(function (MediaConversion $conversion) {
            $conversion->deleteAdditionalFiles();
            $conversion->deleteFile();
        });
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        /** @var class-string<Media> */
        $class = config('media.model', Media::class);

        return $this->belongsTo($class);
    }

    /**
     * @return Attribute<null|string, never>
     */
    public function url(): Attribute
    {
        return Attribute::get(fn () => $this->getUrl());
    }
}
