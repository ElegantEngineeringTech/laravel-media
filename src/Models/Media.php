<?php

namespace Elegantly\Media\Models;

use Closure;
use Elegantly\Media\Casts\GeneratedConversion;
use Elegantly\Media\Casts\GeneratedConversions;
use Elegantly\Media\Contracts\InteractWithMedia;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Events\MediaFileStoredEvent;
use Elegantly\Media\FileDownloaders\FileDownloader;
use Elegantly\Media\Helpers\File;
use Elegantly\Media\Support\ResponsiveImagesConversionsPreset;
use Elegantly\Media\Traits\HasUuid;
use Elegantly\Media\Traits\InteractsWithMediaFiles;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\File as HttpFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\TemporaryDirectory\TemporaryDirectory;

/**
 * @property int $id
 * @property string $uuid
 * @property string $collection_name
 * @property ?string $collection_group
 * @property ?MediaType $type
 * @property ?string $name
 * @property ?string $file_name
 * @property ?string $mime_type
 * @property ?string $extension
 * @property ?int $width
 * @property ?int $height
 * @property ?float $aspect_ratio
 * @property ?string $average_color
 * @property ?int $order_column
 * @property ?Collection<string, GeneratedConversion> $generated_conversions
 * @property ?ArrayObject $metadata
 * @property ?InteractWithMedia $model
 * @property ?string $model_type
 * @property ?int $model_id
 */
class Media extends Model
{
    use HasUuid;
    use InteractsWithMediaFiles;

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
        'generated_conversions' => GeneratedConversions::class,
    ];

    public static function booted()
    {
        static::deleting(function (Media $media) {
            $media->generated_conversions
                ?->keys()
                ->each(function (string $conversion) use ($media) {
                    $media->deleteGeneratedConversionFiles($conversion);
                });

            $media->deleteMediaFiles();
        });
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn () => $this->getUrl());
    }

    public function getConversionKey(string $conversion): string
    {
        return str_replace('.', '.generated_conversions.', $conversion);
    }

    /**
     * Retreive a conversion or nested conversion
     * Ex: $media->getGeneratedConversion('poster.480p')
     */
    public function getGeneratedConversion(string $conversion, ?string $state = null): ?GeneratedConversion
    {
        $generatedConversion = data_get($this->generated_conversions, $this->getConversionKey($conversion));

        if ($state) {
            return $generatedConversion?->state === $state ? $generatedConversion : null;
        }

        return $generatedConversion;
    }

    public function getGeneratedParentConversion(string $conversion, ?string $state = null): ?GeneratedConversion
    {
        $genealogy = explode('.', $conversion);
        $parents = implode('.', array_slice($genealogy, 0, -1));

        return $this->getGeneratedConversion($parents, $state);
    }

    public function hasGeneratedConversion(string $conversion, ?string $state = null): bool
    {
        return (bool) $this->getGeneratedConversion($conversion, $state);
    }

    /**
     * Retreive the path of a conversion or nested conversion
     * Ex: $media->getPath('poster.480p')
     */
    public function getPath(?string $conversion = null, bool $fallback = false): ?string
    {
        if ($conversion) {
            $path = $this->getGeneratedConversion($conversion)?->path;

            return $fallback ? ($path ?? $this->path) : $path;
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
        $prefix = config('media.generated_path_prefix', '');

        $root = Str::of($prefix)
            ->when($prefix, fn ($string) => $string->finish('/'))
            ->append($this->uuid)
            ->finish('/');

        if ($conversion) {
            return $root
                ->append('generated_conversions/')
                ->append(str_replace('.', '/', $this->getConversionKey($conversion)))
                ->finish('/');
        }

        return $root;
    }

    /**
     * Retreive the url of a conversion or nested conversion
     * Ex: $media->getUrl('poster.480p')
     *
     * @param  null|true|string|(callable(): ?string)  $fallback
     */
    public function getUrl(?string $conversion = null, null|true|string|callable $fallback = null): ?string
    {
        $url = null;

        if ($conversion) {
            $url = $this->getGeneratedConversion($conversion)?->getUrl();
        } elseif ($this->path) {
            /** @var null|string $url */
            $url = $this->getDisk()?->url($this->path);
        }

        if ($url) {
            return $url;
        } elseif ($fallback) {
            if ($fallback === true) {
                return $this->getUrl();
            }

            if (is_string($fallback)) {
                return $this->getUrl(conversion: $fallback);
            }

            return value($fallback);
        }

        return $url;
    }

    /**
     * Retreive the temporary url of a conversion or nested conversion
     * Ex: $media->getTemporaryUrl('poster.480p', now()->addHour())
     *
     * @param  null|true|string|(callable(): ?string)  $fallback
     */
    public function getTemporaryUrl(
        ?string $conversion,
        \DateTimeInterface $expiration,
        array $options = [],
        null|true|string|callable $fallback = null
    ): ?string {
        $url = null;

        if ($conversion) {
            $url = $this->getGeneratedConversion($conversion)?->getTemporaryUrl($expiration, $options);
        } elseif ($this->path) {
            /** @var null|string $url */
            $url = $this->getDisk()?->temporaryUrl($this->path, $expiration, $options);
        }

        if ($url) {
            return $url;
        } elseif ($fallback) {
            if ($fallback === true) {
                return $this->getTemporaryUrl(null, $expiration, $options);
            }

            if (is_string($fallback)) {
                return $this->getTemporaryUrl(conversion: $fallback, expiration: $expiration, options: $options);
            }

            return value($fallback);
        }

        return $url;
    }

    public function getWidth(?string $conversion = null, bool $fallback = false): ?int
    {
        if ($conversion) {
            $width = $this->getGeneratedConversion($conversion)?->width;

            return $fallback ? ($width ?? $this->width) : $width;
        }

        return $this->width;
    }

    public function getHeight(?string $conversion = null, bool $fallback = false): ?int
    {
        if ($conversion) {
            $height = $this->getGeneratedConversion($conversion)?->height;

            return $fallback ? ($height ?? $this->height) : $height;
        }

        return $this->height;
    }

    public function getName(?string $conversion = null, bool $fallback = false): ?string
    {
        if ($conversion) {
            $name = $this->getGeneratedConversion($conversion)?->name;

            return match ($fallback) {
                true => $name ?? $this->name,
                false => $name,
            };
        }

        return $this->name;
    }

    public function getFileName(?string $conversion = null, bool $fallback = false): ?string
    {
        if ($conversion) {
            $file_name = $this->getGeneratedConversion($conversion)?->file_name;

            return match ($fallback) {
                true => $file_name ?? $this->file_name,
                false => $file_name,
            };
        }

        return $this->name;
    }

    public function getSize(?string $conversion = null, bool $fallback = false): ?int
    {
        if ($conversion) {
            $size = $this->getGeneratedConversion($conversion)?->size;

            return match ($fallback) {
                true => $size ?? $this->size,
                false => $size,
            };
        }

        return $this->size;
    }

    public function getAspectRatio(?string $conversion = null, bool $fallback = false): ?float
    {
        if ($conversion) {
            $aspect_ratio = $this->getGeneratedConversion($conversion)?->aspect_ratio;

            return match ($fallback) {
                true => $aspect_ratio ?? $this->aspect_ratio,
                false => $aspect_ratio,
            };
        }

        return $this->aspect_ratio;
    }

    public function getMimeType(?string $conversion = null, bool $fallback = false): ?string
    {
        if ($conversion) {
            $mime_type = $this->getGeneratedConversion($conversion)?->mime_type;

            return match ($fallback) {
                true => $mime_type ?? $this->mime_type,
                false => $mime_type,
            };
        }

        return $this->mime_type;
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

    public function extractFileInformation(UploadedFile|HttpFile $file): static
    {
        $this->mime_type = File::mimeType($file);
        $this->extension = File::extension($file);
        $this->size = $file->getSize();
        $this->type = File::type($file->getPathname());

        $dimension = File::dimension($file->getPathname());

        $this->height = $dimension?->getHeight();
        $this->width = $dimension?->getWidth();
        $this->aspect_ratio = $dimension?->getRatio(forceStandards: false)->getValue();
        $this->duration = File::duration($file->getPathname());

        return $this;
    }

    protected function performMediaTransformations(UploadedFile|HttpFile $file): UploadedFile|HttpFile
    {
        if (
            $this->relationLoaded('model') ||
            ($this->model_id && $this->model_type)
        ) {
            $file = $this->model->registerMediaTransformations($this, $file);
            $this->extractFileInformation($file); // refresh file informations
        }

        return $file;
    }

    public function storeFileFromHttpFile(
        UploadedFile|HttpFile $file,
        ?string $collection_name = null,
        ?string $basePath = null,
        ?string $name = null,
        ?string $disk = null,
    ): static {
        $this->collection_name = $collection_name ?? $this->collection_name ?? config('media.default_collection_name');
        $this->disk = $disk ?? $this->disk ?? config('media.disk');

        $this->extractFileInformation($file);

        $file = $this->performMediaTransformations($file);

        $basePath = Str::finish($basePath ?? $this->generateBasePath(), '/');

        $this->name = Str::limit(
            File::sanitizeFilename($name ?? File::name($file)),
            255 - strlen($this->extension ?? '') - strlen($basePath) - 1, // 1 is for the point between the name and the extension
            ''
        );

        $this->file_name = "{$this->name}.{$this->extension}";
        $this->path = $basePath.$this->file_name;

        $path = $this->putFile($file, fileName: $this->file_name);
        event(new MediaFileStoredEvent($this, $path));

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

        $temporaryDirectory = (new TemporaryDirectory)
            ->location(storage_path('media-tmp'))
            ->create();

        $path = FileDownloader::getTemporaryFile($url, $temporaryDirectory);

        $this->storeFileFromHttpFile(new HttpFile($path), $collection_name, $basePath, $name, $disk);

        $temporaryDirectory->delete();

        return $this;
    }

    /**
     * @param  resource  $ressource
     */
    public function storeFileFromRessource(
        $ressource,
        ?string $collection_name = null,
        ?string $basePath = null,
        ?string $name = null,
        ?string $disk = null
    ): static {

        $temporaryDirectory = (new TemporaryDirectory)
            ->location(storage_path('media-tmp'))
            ->create();

        $path = tempnam($temporaryDirectory->path(), 'media-');

        $storage = Storage::build([
            'driver' => 'local',
            'root' => $temporaryDirectory->path(),
        ]);

        $storage->writeStream($path, $ressource);

        $this->storeFileFromHttpFile(new HttpFile($path), $collection_name, $basePath, $name, $disk);

        $temporaryDirectory->delete();

        return $this;
    }

    /**
     * @param  string|UploadedFile|HttpFile|resource  $file
     * @param  (string|UploadedFile|HttpFile)[]  $otherFiles  any other file to store in the same directory
     */
    public function storeFile(
        mixed $file,
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
        } elseif (is_resource($file)) {
            $this->storeFileFromRessource($file, $collection_name, $basePath, $name, $disk);
        } else {
            $this->storeFileFromHttpFile(new HttpFile($file), $collection_name, $basePath, $name, $disk);
        }

        foreach ($otherFiles as $otherFile) {
            $path = $this->putFile($otherFile);
            event(new MediaFileStoredEvent($this, $path));
        }

        return $this;
    }

    /**
     * @param  (string|UploadedFile|HttpFile)[]  $otherFiles  any other file to store in the same directory
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
            $path = $this->putFile($otherFile);
            event(new MediaFileStoredEvent($this, $path));
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
        $temporaryDirectory = (new TemporaryDirectory)
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
        $type = File::type($file->getPathname());
        $dimension = File::dimension($file->getPathname());

        $existingConversion = $this->getGeneratedConversion($conversion);

        if ($existingConversion) {
            $this->deleteGeneratedConversionFiles($conversion);
        }

        $generatedConversion = new GeneratedConversion(
            name: $name,
            extension: $extension,
            file_name: $file_name,
            path: Str::of($basePath ?? $this->generateBasePath($conversion))->finish('/')->append($file_name),
            mime_type: $mime_type,
            type: $type,
            state: $state,
            disk: $this->disk,
            height: $dimension?->getHeight(),
            width: $dimension->getWidth(),
            aspect_ratio: $dimension?->getRatio(forceStandards: false)->getValue(),
            size: $file->getSize(),
            duration: File::duration($file->getPathname()),
            created_at: $existingConversion?->created_at
        );

        $this->putGeneratedConversion($conversion, $generatedConversion);

        $path = $generatedConversion->putFile($file, fileName: $generatedConversion->file_name);
        event(new MediaFileStoredEvent($this, $path));

        $this->save();

        return $generatedConversion;
    }

    /**
     * @param  null|Closure(GeneratedConversion $item):bool  $when
     */
    public function moveGeneratedConversion(
        string $conversion,
        ?string $disk = null,
        ?string $path = null,
        ?Closure $when = null
    ): ?GeneratedConversion {
        $generatedConversion = $this->getGeneratedConversion($conversion);

        if (! $generatedConversion) {
            return null;
        }

        if ($when && ! $when($generatedConversion)) {
            return $generatedConversion;
        }

        if (! $generatedConversion->disk || ! $generatedConversion->path) {
            return $generatedConversion;
        }

        $newDisk = $disk ?? $generatedConversion->disk;
        $newPath = $path ?? $generatedConversion->path;

        if (
            $newDisk === $generatedConversion->disk &&
            $newPath === $generatedConversion->path
        ) {
            return $generatedConversion;
        }

        $generatedConversion->copyFileTo(
            disk: $newDisk,
            path: $newPath
        );

        $generatedConversion->deleteFile();

        $generatedConversion->disk = $newDisk;
        $generatedConversion->path = $newPath;

        $this->putGeneratedConversion(
            $conversion,
            $generatedConversion
        );

        $this->save();

        return $generatedConversion;
    }

    public function moveFile(
        ?string $disk = null,
        ?string $path = null,
    ): static {

        if (! $this->disk || ! $this->path) {
            return $this;
        }

        $newDisk = $disk ?? $this->disk;
        $newPath = $path ?? $this->path;

        if (
            $newDisk === $this->disk &&
            $newPath === $this->path
        ) {
            return $this;
        }

        $this->copyFileTo(
            disk: $newDisk,
            path: $newPath
        );

        $this->deleteFile();

        $this->disk = $newDisk;
        $this->path = $newPath;

        $this->save();

        return $this;
    }

    /**
     * Recursively move generated and nested conversions files to a new disk
     *
     * @param  null|Closure(GeneratedConversion $item):bool  $when
     */
    protected function moveGeneratedConversionToDisk(
        string $disk,
        string $conversion,
        ?Closure $when = null
    ): ?GeneratedConversion {
        $generatedConversion = $this->moveGeneratedConversion(
            conversion: $conversion,
            disk: $disk,
            when: $when
        );

        if (! $generatedConversion) {
            return null;
        }

        foreach ($generatedConversion->generated_conversions->keys() as $childConversionName) {
            $this->moveGeneratedConversionToDisk(
                disk: $disk,
                conversion: "{$conversion}.{$childConversionName}",
                when: $when,
            );
        }

        return $generatedConversion;
    }

    /**
     * @param  null|Closure(GeneratedConversion|static $item):bool  $when
     */
    public function moveToDisk(
        string $disk,
        ?Closure $when = null
    ): static {

        if ($when && ! $when($this)) {
            return $this;
        }

        if ($this->generated_conversions) {
            foreach ($this->generated_conversions->keys() as $conversionName) {
                $this->moveGeneratedConversionToDisk(
                    disk: $disk,
                    conversion: $conversionName,
                    when: $when
                );
            }
        }

        return $this->moveFile(
            disk: $disk
        );
    }

    public function getResponsiveImages(
        ?string $conversion = null,
        array $widths = ResponsiveImagesConversionsPreset::DEFAULT_WIDTH
    ): Collection {
        return collect($widths)
            ->when(
                $conversion,
                fn (Collection $collection) => $collection->map(fn (int $width) => $this->getGeneratedConversion("{$conversion}.{$width}")),
                fn (Collection $collection) => $collection->map(fn (int $width) => $this->getGeneratedConversion($width)),
            )
            ->filter();
    }

    /**
     * Exemple: elva-fairy-480w.jpg 480w, elva-fairy-800w.jpg 800w
     */
    public function getSrcset(?string $conversion = null): Collection
    {
        return $this
            ->getResponsiveImages($conversion)
            ->filter(fn (GeneratedConversion $generatedConversion) => $generatedConversion->getUrl())
            ->map(fn (GeneratedConversion $generatedConversion) => "{$generatedConversion->getUrl()} {$generatedConversion->width}w");
    }

    /**
     * @param  null|(Closure(null|int $previous): int)  $sequence
     * @return EloquentCollection<int, static>
     */
    public static function reorder(array $keys, ?Closure $sequence = null, string $using = 'id'): EloquentCollection
    {
        /** @var EloquentCollection<int, static> */
        $models = static::query()
            ->whereIn($using, $keys)
            ->get();

        $models = $models->sortBy(function (Media $model) use ($keys, $using) {
            return array_search($model->{$using}, $keys);
        })->values();

        $previous = $sequence ? null : -1;

        foreach ($models as $model) {

            $model->order_column = $sequence ? $sequence($previous) : ($previous + 1);

            $previous = $model->order_column;

            $model->save();
        }

        return $models;
    }

    public function deleteGeneratedConversion(string $conversion): ?GeneratedConversion
    {
        $generatedConversion = $this->getGeneratedConversion($conversion);

        if (! $generatedConversion) {
            return null;
        }

        $this->deleteGeneratedConversionFiles($conversion);
        $this->forgetGeneratedConversion($conversion);
        $this->save();

        return $generatedConversion;
    }

    public function deleteGeneratedConversions(): static
    {
        $this->generated_conversions
            ?->keys()
            ->each(function (string $conversion) {
                $this->deleteGeneratedConversionFiles($conversion);
            });

        $this->generated_conversions = collect();
        $this->save();

        return $this;
    }

    /**
     * You can override this function to customize how files are deleted
     */
    public function deleteGeneratedConversionFiles(string $conversion): static
    {
        $generatedConversion = $this->getGeneratedConversion($conversion);

        if (! $generatedConversion) {
            return $this;
        }

        $generatedConversion->generated_conversions
            ->keys()
            ->each(function (string $childConversion) use ($conversion) {
                $this->deleteGeneratedConversionFiles("{$conversion}.{$childConversion}");
            });

        $generatedConversion->deleteFile();

        return $this;
    }

    /**
     * You can override this function to customize how files are deleted
     */
    protected function deleteMediaFiles(): static
    {
        $this->deleteFile();

        return $this;
    }
}
