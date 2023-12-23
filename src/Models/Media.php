<?php

namespace Finller\Media\Models;

use Finller\Media\Casts\GeneratedConversion;
use Finller\Media\Casts\GeneratedConversions;
use Finller\Media\Enums\MediaType;
use Finller\Media\FileDownloaders\FileDownloader;
use Finller\Media\Helpers\File;
use Finller\Media\Traits\HasUuid;
use Finller\Media\Traits\InteractsWithMediaFiles;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\File as HttpFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\TemporaryDirectory\TemporaryDirectory;

/**
 * @property int $id
 * @property string $uuid
 * @property string $collection_name
 * @property ?string $collection_group
 * @property ?string $disk
 * @property ?string $path
 * @property ?MediaType $type
 * @property ?string $name
 * @property ?string $file_name
 * @property ?int $size
 * @property ?string $mime_type
 * @property ?string $extension
 * @property ?int $width
 * @property ?int $height
 * @property ?float $aspect_ratio
 * @property ?string $average_color
 * @property ?float $duration in miliseconds
 * @property ?int $order_column
 * @property ?Collection<string, GeneratedConversion> $generated_conversions
 * @property ?ArrayObject $metadata
 * @property ?Model $model
 */
class Media extends Model
{
    use HasUuid;
    use InteractsWithMediaFiles;

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

    /**
     * Retreive a conversion or nested conversion
     * Ex: $media->getGeneratedConversion('poster.480p')
     */
    public function getGeneratedConversion(string $conversion): ?GeneratedConversion
    {
        return data_get($this->generated_conversions, $this->getConversionKey($conversion));
    }

    public function getGeneratedParentConversion(string $conversion): ?GeneratedConversion
    {
        $genealogy = explode('.', $conversion);
        $parents = implode('.', array_slice($genealogy, 0, -1));

        return $this->getGeneratedConversion($parents);
    }

    public function hasGeneratedConversion(string $conversion): bool
    {
        return (bool) $this->getGeneratedConversion($conversion);
    }

    /**
     * Retreive the path of a conversion or nested conversion
     * Ex: $media->getPath('poster.480p')
     */
    public function getPath(?string $conversion = null): ?string
    {
        if ($conversion) {
            return $this->getGeneratedConversion($conversion)?->path;
        }

        return $this->path;
    }

    /**
     * Generate the default base path for storing files
     * uuid/
     *  files
     *  /generated_conversions
     *      /conversionName
     *      files
     */
    public function generateBasePath(?string $conversion = null): string
    {
        if ($conversion) {
            return "{$this->uuid}/generated_conversions/".str_replace('.', '/', $this->getConversionKey($conversion)).'/';
        }

        return "{$this->uuid}/";
    }

    /**
     * Retreive the url of a conversion or nested conversion
     * Ex: $media->getUrl('poster.480p')
     */
    public function getUrl(?string $conversion = null)
    {
        return $this->getDisk()->url($this->getPath($conversion));
    }

    public function putGeneratedConversion(string $conversion, GeneratedConversion $generatedConversion): static
    {
        $genealogy = explode('.', $conversion);

        if (count($genealogy) > 1) {
            $child = Arr::last($genealogy);
            $parents = implode('.', array_slice($genealogy, 0, -1));
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
            $parents = implode('.', array_slice($genealogy, 0, -1));
            $conversion = $this->getGeneratedConversion($parents);
            $conversion->generated_conversions->forget($child);
        } else {
            $this->generated_conversions->forget($conversion);
        }

        return $this;
    }

    public function storeFileFromHttpFile(
        UploadedFile|HttpFile $file,
        ?string $collection_name = null,
        ?string $basePath = null,
        ?string $name = null,
        ?string $disk = null,
    ) {
        $this->collection_name = $collection_name ?? $this->collection_name ?? config('media.default_collection_name');
        $this->disk = $disk ?? $this->disk ?? config('filesystems.default');

        $this->mime_type = File::mimeType($file);
        $this->extension = File::extension($file);
        $this->size = $file->getSize();
        $this->type = MediaType::tryFromMimeType($this->mime_type);

        $dimension = File::dimension($file->getPathname(), type: $this->type);

        $this->height = $dimension?->getHeight();
        $this->width = $dimension?->getWidth();
        $this->aspect_ratio = $dimension?->getRatio(forceStandards: false)->getValue();
        $this->duration = File::duration($file->getPathname());

        $this->name = File::sanitizeFilename($name ?? File::name($file));

        $this->file_name = "{$this->name}.{$this->extension}";
        $this->path = Str::finish($basePath ?? $this->generateBasePath(), '/').$this->file_name;

        $this->putFile($file, fileName: $this->file_name);

        $this->save();

        return $this;
    }

    public function storeFileFromUrl(
        string $url,
        ?string $collection_name = null,
        ?string $basePath = null,
        ?string $name = null,
        ?string $disk = null,
    ): static {

        $temporaryDirectory = (new TemporaryDirectory())
            ->location(storage_path('media-tmp'))
            ->create();

        $path = FileDownloader::getTemporaryFile($url, $temporaryDirectory);

        $this->storeFileFromHttpFile(new HttpFile($path), $collection_name, $basePath, $name, $disk);

        $temporaryDirectory->delete();

        return $this;
    }

    /**
     * @param  (string|UploadedFile|HttpFile)[]  $otherFiles any other file to store in the same directory
     */
    public function storeFile(
        string|UploadedFile|HttpFile $file,
        ?string $collection_name = null,
        ?string $basePath = null,
        ?string $name = null,
        ?string $disk = null,
        array $otherFiles = []
    ): static {
        if ($file instanceof UploadedFile || $file instanceof HttpFile) {
            $this->storeFileFromHttpFile($file, $collection_name, $basePath, $name, $disk);
        } elseif (filter_var($file, FILTER_VALIDATE_URL)) {
            $this->storeFileFromUrl($file, $collection_name, $basePath, $name, $disk);
        } else {
            $this->storeFileFromHttpFile(new HttpFile($file), $collection_name, $basePath, $name, $disk);
        }

        foreach ($otherFiles as $otherFile) {
            $this->putFile($otherFile);
        }

        return $this;
    }

    /**
     * @param  (string|UploadedFile|HttpFile)[]  $otherFiles any other file to store in the same directory
     */
    public function storeConversion(
        string|UploadedFile|HttpFile $file,
        string $conversion,
        ?string $name = null,
        ?string $basePath = null,
        string $state = 'success',
        array $otherFiles = []
    ): GeneratedConversion {

        if ($file instanceof UploadedFile || $file instanceof HttpFile) {
            $generatedConversion = $this->storeConversionFromHttpFile($file, $conversion, $name, $basePath, $state);
        } elseif (filter_var($file, FILTER_VALIDATE_URL)) {
            $generatedConversion = $this->storeConversionFromUrl($file, $conversion, $name, $basePath, $state);
        } else {
            $generatedConversion = $this->storeConversionFromHttpFile(new HttpFile($file), $conversion, $name, $basePath, $state);
        }

        foreach ($otherFiles as $otherFile) {
            $this->putFile($otherFile);
        }

        return $generatedConversion;
    }

    public function storeConversionFromUrl(
        string $url,
        string $conversion,
        ?string $name = null,
        ?string $basePath = null,
        string $state = 'success',
    ): GeneratedConversion {
        $temporaryDirectory = (new TemporaryDirectory())
            ->location(storage_path('media-tmp'))
            ->create();

        $path = FileDownloader::getTemporaryFile($url, $temporaryDirectory);

        $generatedConversion = $this->storeConversionFromHttpFile(new HttpFile($path), $conversion, $name, $basePath, $state);

        $temporaryDirectory->delete();

        return $generatedConversion;
    }

    public function storeConversionFromHttpFile(
        UploadedFile|HttpFile $file,
        string $conversion,
        ?string $name = null,
        ?string $basePath = null,
        string $state = 'success',
    ): GeneratedConversion {
        $name = File::sanitizeFilename($name ?? File::name($file->getPathname()));

        $extension = File::extension($file);
        $file_name = "{$name}.{$extension}";
        $mime_type = File::mimeType($file);
        $type = MediaType::tryFromMimeType($mime_type);
        $dimension = File::dimension($file->getPathname(), type: $type);

        $existingConversion = $this->getGeneratedConversion($name);

        $existingConversion?->delete();

        $generatedConversion = new GeneratedConversion(
            name: $name,
            extension: $extension,
            file_name: $file_name,
            path: Str::finish($basePath ?? $this->generateBasePath($conversion), '/').$file_name,
            mime_type: $mime_type,
            type: $type,
            state: $state,
            disk: $this->disk,
            height: $dimension?->getHeight(),
            width: $dimension->getWidth(),
            aspect_ratio: $dimension?->getRatio(forceStandards: false)->getValue(),
            size: $file->getSize(),
            created_at: $existingConversion?->created_at
        );

        $this->putGeneratedConversion($conversion, $generatedConversion);

        $generatedConversion->putFile($file, fileName: $generatedConversion->file_name);

        $this->save();

        return $generatedConversion;
    }

    public function deleteGeneratedConversion(string $converion): GeneratedConversion
    {
        $generatedConversion = $this->getGeneratedConversion($converion);
        $generatedConversion?->delete();
        $this->forgetGeneratedConversion($converion);
        $this->save();

        return $generatedConversion;
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
