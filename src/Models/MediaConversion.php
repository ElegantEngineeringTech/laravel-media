<?php

namespace Elegantly\Media\Models;

use Carbon\Carbon;
use Elegantly\Media\Concerns\InteractWithFiles;
use Elegantly\Media\Database\Factories\MediaConversionFactory;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Events\MediaFileStoredEvent;
use Elegantly\Media\FileDownloaders\FileDownloader;
use Elegantly\Media\Helpers\File;
use Elegantly\Media\TemporaryDirectory;
use Elegantly\Media\Traits\HasUuid;
use Exception;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
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
 * @property ?string $state
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
 * @property ?ArrayObject $metadata
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

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => MediaType::class,
        'metadata' => AsArrayObject::class,
        'state_set_at' => 'datetime',
    ];

    public static function booted()
    {
        static::deleting(function (MediaConversion $conversion) {
            $conversion->deleteFile();
        });
    }

    /**
     * @return BelongsTo<Media, MediaConversion>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
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
                $path = FileDownloader::download(
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
