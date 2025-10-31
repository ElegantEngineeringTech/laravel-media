<?php

declare(strict_types=1);

namespace Elegantly\Media\Models;

use Carbon\Carbon;
use Elegantly\Media\Concerns\InteractWithFiles;
use Elegantly\Media\Database\Factories\MediaConversionFactory;
use Elegantly\Media\Enums\MediaConversionState;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Events\MediaFileStoredEvent;
use Elegantly\Media\Helpers\File;
use Elegantly\Media\HttpFileDownloaders\HttpFileDownloader;
use Elegantly\Media\TemporaryDirectory;
use Elegantly\Media\Traits\HasUuid;
use Exception;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\File as HttpFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

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
    /** @use HasFactory<MediaConversionFactory>  */
    use HasFactory;

    use HasUuid;
    use InteractWithFiles;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

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

    /**
     * @param  string|UploadedFile|HttpFile|resource  $file
     */
    public function storeFile(
        mixed $file,
        string $destination,
        ?string $name = null,
        ?string $disk = null,
    ): static {
        if ($file instanceof UploadedFile || $file instanceof HttpFile) {
            return $this->storeFileFromHttpFile($file, $destination, $name, $disk);
        }

        if (
            (is_string($file) && filter_var($file, FILTER_VALIDATE_URL)) ||
            ! is_string($file)
        ) {
            return TemporaryDirectory::callback(function ($temporaryDirectory) use ($file, $destination, $name, $disk) {
                $path = HttpFileDownloader::download(
                    file: $file,
                    destination: $temporaryDirectory->path()
                );

                return $this->storeFileFromHttpFile(new HttpFile($path), $destination, $name, $disk);
            });
        }

        return $this->storeFileFromHttpFile(new HttpFile($file), $destination, $name, $disk);
    }

    public function storeFileFromHttpFile(
        UploadedFile|HttpFile $file,
        string $destination,
        ?string $name = null,
        ?string $disk = null,
    ): static {

        $name ??= File::name($file) ?? Str::random(6);
        $disk ??= $this->disk ?? config()->string('media.disk');

        $path = $this->putFile(
            disk: $disk,
            destination: $destination,
            file: $file,
            name: $name,
        );

        if (! $path) {
            throw new Exception("Storing Media Conversion File '{$file->getPath()}' to disk '{$disk}' at '{$destination}' failed.");
        }

        $this->save();

        event(new MediaFileStoredEvent($this));

        return $this;
    }
}
