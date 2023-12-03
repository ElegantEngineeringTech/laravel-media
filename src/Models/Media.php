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
use Illuminate\Support\Facades\File as SupportFile;
use Illuminate\Support\Str;
use Spatie\TemporaryDirectory\TemporaryDirectory;

/**
 * @property int $id
 * @property string $uuid
 * @property string $collection_name
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
     * Generate the default base path for storing files
     * uuid/
     *  files
     *  /generated_conversions
     *      /conversionName
     *      files
     */
    public function generateBasePath(string $conversion = null): string
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

    public function storeFileFromHttpFile(
        UploadedFile|HttpFile $file,
        string $collection_name = null,
        string $basePath = null,
        string $name = null,
        string $disk = null,
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

        $this->name = File::sanitizeFilename($name ?? File::name($file));

        $this->file_name = "{$this->name}.{$this->extension}";
        $this->path = Str::finish($basePath ?? $this->generateBasePath(), '/').$this->file_name;

        $this->putFile($file, fileName: $this->file_name);

        $this->save();

        return $this;
    }

    public function storeFileFromUrl(
        string $url,
        string $collection_name = null,
        string $basePath = null,
        string $name = null,
        string $disk = null,
    ): static {

        $temporaryDirectory = (new TemporaryDirectory())
            ->location(storage_path('media-tmp'))
            ->create();

        $path = FileDownloader::getTemporaryFile($url, $temporaryDirectory);

        $this->storeFileFromHttpFile(new HttpFile($path), $collection_name, $basePath, $name, $disk);

        $temporaryDirectory->delete();

        return $this;
    }

    public function storeFile(
        string|UploadedFile|HttpFile $file,
        string $collection_name = null,
        string $basePath = null,
        string $name = null,
        string $disk = null
    ): static {
        if ($file instanceof UploadedFile || $file instanceof HttpFile) {
            return $this->storeFileFromHttpFile($file, $collection_name, $basePath, $name, $disk);
        }

        if (filter_var($file, FILTER_VALIDATE_URL)) {
            return $this->storeFileFromUrl($file, $collection_name, $basePath, $name, $disk);
        }

        return $this;
    }

    /**
     * @param  (string|HttpFile)[]  $otherFiles
     */
    public function storeConversion(
        HttpFile|string $file,
        string $conversion,
        string $name = null,
        string $basePath = null,
        string $state = 'success',
        array $otherFiles = []
    ): GeneratedConversion {
        $file = is_string($file) ? new HttpFile($file) : $file;
        $name = File::sanitizeFilename($name ?? SupportFile::name($file->getPathname()));

        $extension = $file->guessExtension();
        $file_name = "{$name}.{$extension}";
        $mime_type = $file->getMimeType();
        $type = MediaType::tryFromMimeType($mime_type);
        $dimension = File::dimension($file->getPathname(), type: $type);

        $generatedConversion = new GeneratedConversion(
            name: $name,
            extension: $extension,
            file_name: $file_name,
            path: ($basePath ?? $this->generateBasePath($conversion)).$file_name,
            mime_type: $mime_type,
            type: $type,
            state: $state,
            disk: $this->disk,
            height: $dimension?->getHeight(),
            width: $dimension->getWidth(),
            aspect_ratio: $dimension?->getRatio(forceStandards: false)->getValue(),
            size: $file->getSize(),
        );

        $this->putGeneratedConversion($conversion, $generatedConversion);

        $generatedConversion->putFile($file, fileName: $generatedConversion->file_name);

        foreach ($otherFiles as $otherFile) {
            $generatedConversion->putFile($otherFile);
        }

        $this->save();

        return $generatedConversion;
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
