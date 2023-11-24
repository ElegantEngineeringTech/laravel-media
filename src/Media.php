<?php

namespace Finller\LaravelMedia;

use Finller\LaravelMedia\Casts\GeneratedConversion;
use Finller\LaravelMedia\Casts\GeneratedConversions;
use Finller\LaravelMedia\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
 * @property ?string $average_color
 * @property ?int $order_column
 * @property ?Collection<string, GeneratedConversion> $generated_conversions
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
        'generated_conversions' => GeneratedConversions::class,
    ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function getConversionKey(string $conversion): string
    {
        return str_replace('.', '.conversions.', $conversion);
    }

    public function getGeneratedConversion(string $conversion): ?GeneratedConversion
    {
        return data_get($this->generated_conversions, $this->getConversionKey($conversion));
    }

    public function hasGeneratedConversion(string $conversion): bool
    {
        return (bool) $this->getGeneratedConversion($conversion);
    }

    /**
     * Retreive the path of a conversion or nested conversion
     * Ex: $media->getPath('poster.480p')
     */
    public function getPath(string $conversion = null): ?string
    {
        if ($conversion) {
            return $this->getGeneratedConversion($conversion)?->path;
        }

        return $this->path;
    }

    /**
     * Retreive the url of a conversion or nested conversion
     * Ex: $media->getUrl('poster.480p')
     */
    public function getUrl(string $conversion = null)
    {
        return Storage::disk($this->disk)->url($this->getPath($conversion));
    }

    public function addGeneratedConversion(string $name, GeneratedConversion $generatedConversion, string $parent = null): static
    {
        if ($parent) {
            $conversion = $this->getGeneratedConversion($parent);
            $conversion->conversions->put($name, $generatedConversion);
        }

        $this->generated_conversions->put($name, $generatedConversion);

        return $this;
    }

    public function storeFileFromUpload(UploadedFile $file, string $path = null, string $name = null)
    {
        $this->name = Str::slug(
            $name ?? $file->getClientOriginalName(),
            dictionary: ['@' => 'at', '+' => '-']
        );

        $this->mime_type = $file->getMimeType() ?? $file->getClientMimeType();
        $this->extension = $file->guessExtension() ?? $file->clientExtension();
        $this->size = $file->getSize();

        $this->path = $path ?? "/{$this->uuid}/{$this->name}.{$this->extension}";

        $file->storeAs(
            path: $this->path,
            name: $this->name,
            options: [
                'disk' => $this->disk,
            ]
        );
    }

    public function storeFile(string|UploadedFile $file, string $name = null)
    {
        if ($file instanceof UploadedFile) {
            return $this->storeFileFromUpload($file, $name);
        }
    }
}

// media/uuid
//     -> files
//     /conversions
//         /poster
//             -> files
//             /conversions
//                 /480p
//                     -> files
//                 /720p
//                     -> files
//                 /1080p
//                     -> files
//         /hls
//             ->files
