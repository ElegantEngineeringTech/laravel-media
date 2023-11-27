<?php

namespace Finller\LaravelMedia;

use Finller\LaravelMedia\Casts\GeneratedConversion;
use Finller\LaravelMedia\Casts\GeneratedConversions;
use Finller\LaravelMedia\Enums\MediaType;
use Finller\LaravelMedia\Helpers\File;
use Finller\LaravelMedia\Traits\HasUuid;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\File as HttpFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File as SupportFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Spatie\TemporaryDirectory\TemporaryDirectory;

/**
 * @property int $id
 * @property string $uuid
 * @property string $disk
 * @property string $path
 * @property MediaType $type
 * @property string $name
 * @property string $file_name
 * @property int $size
 * @property ?string $mime_type
 * @property ?string $extension
 * @property ?string $collection_name
 * @property ?int $width
 * @property ?int $height
 * @property ?float $aspect_ratio
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
        'type' => MediaType::class,
        'metadata' => AsArrayObject::class,
        'generated_conversions' => GeneratedConversions::class,
    ];

    protected $attributes = [
        'generated_conversions' => '[]',
    ];

    public static function booted()
    {
        static::deleted(function (Media $media) {
            $media->deleteDirectory();
            $media->deleteGeneratedConversions();
        });
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function getConversionKey(string $conversion): string
    {
        return str_replace('.', '.generated_conversions.', $conversion);
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

    protected function generateBasePath(string $conversion = null): string
    {
        if ($conversion) {
            return "/{$this->uuid}/generated_conversions/" . str_replace('.', '/', $this->getConversionKey($conversion)) . '/';
        }

        return "/{$this->uuid}/";
    }

    public function getDisk(): Filesystem
    {
        return Storage::disk($this->disk);
    }

    /**
     * @return null|resource
     */
    public function readStream()
    {
        return $this->getDisk()->readStream($this->path);
    }

    /**
     * @param  string  $path including the file name
     */
    public function copyFileTo(string $path): static
    {
        file_put_contents($path, $this->readStream());

        return $this;
    }

    public function makeTemporaryFileCopy(TemporaryDirectory $temporaryDirectory = null): string|false
    {
        $temporaryDirectory ??= (new TemporaryDirectory())->deleteWhenDestroyed()->create();

        $path = $temporaryDirectory->path($this->file_name);

        $this->copyFileTo($path);

        return $path;
    }

    /**
     * Retreive the url of a conversion or nested conversion
     * Ex: $media->getUrl('poster.480p')
     */
    public function getUrl(string $conversion = null)
    {
        return $this->getDisk()->url($this->getPath($conversion));
    }

    public function putGeneratedConversion(string $conversion, GeneratedConversion $generatedConversion): static
    {
        $genealogy = explode('.', $conversion);

        if (count($genealogy) > 1) {
            $child = Arr::last($genealogy);
            $parents = implode('.', array_slice($genealogy, 0, count($genealogy) - 1));
            $conversion = $this->getGeneratedConversion($parents);
            $conversion->generated_conversions->put($child, $generatedConversion);
        } else {
            $this->generated_conversions->put($conversion, $generatedConversion);
        }

        return $this;
    }

    public function forgetGeneratedConversion(string $conversion): static
    {
        $genealogy = explode('.', $conversion);

        if (count($genealogy) > 1) {
            $child = Arr::last($genealogy);
            $parents = implode('.', array_slice($genealogy, 0, count($genealogy) - 1));
            $conversion = $this->getGeneratedConversion($parents);
            $conversion->generated_conversions->forget($child);
        } else {
            $this->generated_conversions->forget($conversion);
        }

        return $this;
    }

    public function humanReadableSize(): string
    {
        return Number::fileSize($this->size);
    }

    public function storeFileFromUpload(
        UploadedFile $file,
        string $collection_name = null,
        string $basePath = null,
        string $name = null,
        string $disk = null,
    ): static {
        $this->collection_name = $collection_name ?? $this->collection_name ?? config('media.default_collection_name');
        $this->disk = $disk ?? $this->disk ?? config('filesystems.default');

        $this->mime_type = $file->getMimeType() ?? $file->getClientMimeType();
        $this->extension = $file->guessExtension() ?? $file->clientExtension();
        $this->size = $file->getSize();
        $this->type = MediaType::tryFromMimeType($this->mime_type);

        $dimension = File::dimension($file->getPathname(), type: $this->type);

        $this->height = $dimension?->getHeight();
        $this->width = $dimension?->getWidth();
        $this->aspect_ratio = $dimension?->getRatio(forceStandards: false)->getValue();

        $this->name = Str::slug(
            $name ?? SupportFile::name($file->getClientOriginalName()),
            dictionary: ['@' => 'at', '+' => '-']
        );

        $this->file_name = "{$this->name}.{$this->extension}";
        $this->path = ($basePath ?? $this->generateBasePath()) . $this->file_name;

        $file->storeAs(
            path: SupportFile::dirname($this->path),
            name: $this->file_name,
            options: [
                'disk' => $this->disk,
            ]
        );

        $this->save();

        return $this;
    }

    public function storeFile(
        string|UploadedFile $file,
        string $collection_name = null,
        string $basePath = null,
        string $name = null,
        string $disk = null
    ): static {
        if ($file instanceof UploadedFile) {
            return $this->storeFileFromUpload($file, $collection_name, $basePath, $name, $disk);
        }

        return $this;
    }

    public function storeConversion(
        HttpFile|string $file,
        string $conversion,
        string $name = null,
        string $basePath = null,
    ): GeneratedConversion {
        $file = $file instanceof HttpFile ? $file : new HttpFile($file);

        $extension = $file->guessExtension();
        $name = File::sanitizeFilename($name ?? SupportFile::name($file->getPathname()));
        $file_name = "{$name}.{$extension}";

        $mime_type = $file->getMimeType();
        $type = MediaType::tryFromMimeType($mime_type);
        $dimension = File::dimension($file->getPathname(), type: $type);

        $generatedConversion = new GeneratedConversion(
            name: $name,
            extension: $extension,
            file_name: $file_name,
            path: ($basePath ?? $this->generateBasePath($conversion)) . $file_name,
            mime_type: $mime_type,
            type: $type,
            state: 'success',
            disk: $this->disk,
            height: $dimension?->getHeight(),
            width: $dimension->getWidth(),
            aspect_ratio: $dimension?->getRatio(forceStandards: false)->getValue(),
            size: $file->getSize(),
        );

        $this->putGeneratedConversion($conversion, $generatedConversion);

        Storage::disk($generatedConversion->disk)->putFileAs(
            SupportFile::dirname($generatedConversion->path),
            $file,
            $generatedConversion->file_name
        );

        $this->save();

        return $generatedConversion;
    }

    public function deleteDirectory(): static
    {
        $this->getDisk()->deleteDirectory(
            SupportFile::dirname($this->path)
        );

        return $this;
    }

    public function deleteGeneratedConversion(string $converion): static
    {
        $this->getGeneratedConversion($converion)?->delete();
        $this->forgetGeneratedConversion($converion);
        $this->save();

        return $this;
    }

    public function deleteGeneratedConversions(): static
    {
        $this->generated_conversions->each(fn (GeneratedConversion $generatedConversion) => $generatedConversion->delete());
        $this->generated_conversions = collect();
        $this->save();

        return $this;
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
