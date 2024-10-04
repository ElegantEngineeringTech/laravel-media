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
 * @property ?string $path
 * @property ?string $name
 * @property ?string $file_name
 * @property ?string $mime_type
 * @property ?string $extension
 * @property ?int $width
 * @property ?int $height
 * @property ?float $aspect_ratio
 * @property ?string $average_color
 * @property ?int $size
 * @property ?int $order_column
 * @property ?float $duration
 * @property ?Collection<string, GeneratedConversion> $generated_conversions
 * @property ?ArrayObject $metadata
 * @property ?InteractWithMedia $model
 * @property ?string $model_type
 * @property ?int $model_id
 * @property-read ?string $url
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

    public function makeGeneratedConversionKey(string $conversion): string
    {
        return str_replace('.', '.generated_conversions.', $conversion);
    }

    /**
     * Retreive a conversion or nested conversion
     * Ex: $media->getGeneratedConversion('poster.480p')
     */
    public function getGeneratedConversion(string $conversion, ?string $state = null): ?GeneratedConversion
    {
        $generatedConversion = data_get(
            $this->generated_conversions,
            $this->makeGeneratedConversionKey($conversion)
        );

        if ($state) {
            return $generatedConversion?->state === $state ? $generatedConversion : null;
        }

        return $generatedConversion;
    }

    public function hasGeneratedConversion(string $conversion, ?string $state = null): bool
    {
        return (bool) $this->getGeneratedConversion($conversion, $state);
    }

    /**
     * Generate the default base path for storing files
     * uuid/
     *  files
     *  /generated_conversions
     *      /conversionName
     *      files
     */
    public function makePath(
        ?string $conversion = null,
        ?string $fileName = null
    ): string {
        $prefix = config('media.generated_path_prefix', '');

        $root = Str::of($prefix)
            ->when($prefix, fn ($string) => $string->finish('/'))
            ->append($this->uuid)
            ->finish('/');

        if ($conversion) {
            return $root
                ->append('generated_conversions/')
                ->append(str_replace('.', '/', $this->makeGeneratedConversionKey($conversion)))
                ->finish('/')
                ->append($fileName ?? '');
        }

        return $root->append($fileName ?? '');
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

        $basePath = Str::finish($basePath ?? $this->makePath(), '/');

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
            path: Str::of($basePath ?? $this->makePath($conversion))->finish('/')->append($file_name),
            mime_type: $mime_type,
            type: $type,
            state: $state,
            disk: $this->disk,
            height: $dimension?->getHeight(),
            width: $dimension?->getWidth(),
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

        $this
            ->deleteGeneratedConversionFiles($conversion)
            ->forgetGeneratedConversion($conversion)
            ->save();

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

    // Attributes Getters ----------------------------------------------------------------------

    /**
     * Retreive the path of a conversion or nested conversion
     * Ex: $media->getPath('poster.480p')
     */
    public function getPath(
        ?string $conversion = null,
        null|bool|string|array $fallback = null,
    ): ?string {
        $path = null;

        if ($conversion) {
            $path = $this->getGeneratedConversion($conversion)?->path;
        } elseif ($this->path) {
            $path = $this->path;
        }

        if ($path) {
            return $path;
        } elseif ($fallback === true) {
            return $this->getPath();
        } elseif (is_string($fallback)) {
            return $this->getPath(
                conversion: $fallback,
            ) ?? $fallback;
        } elseif (is_array($fallback)) {
            return $this->getPath(
                conversion: array_shift($fallback),
                fallback: $fallback,
            );
        }

        return null;
    }

    /**
     * Retreive the url of a conversion or nested conversion
     * Ex: $media->getUrl('poster.480p')
     *
     * @param  null|bool|string|array<int, string>  $fallback
     */
    public function getUrl(
        ?string $conversion = null,
        null|bool|string|array $fallback = null,
        ?array $parameters = null,
    ): ?string {
        $url = null;

        if ($conversion) {
            $url = $this->getGeneratedConversion($conversion)?->getUrl();
        } elseif ($this->path) {
            /** @var null|string $url */
            $url = $this->getDisk()?->url($this->path);
        }

        if ($url) {

            if (empty($parameters)) {
                return $url;
            }

            return $url.'?'.http_build_query($parameters);
        } elseif ($fallback === true) {
            return $this->getUrl(
                parameters: $parameters,
            );
        } elseif (is_string($fallback)) {
            return $this->getUrl(
                conversion: $fallback,
                parameters: $parameters
            ) ?? $fallback;
        } elseif (is_array($fallback)) {
            return $this->getUrl(
                conversion: array_shift($fallback),
                fallback: $fallback,
                parameters: $parameters
            );
        }

        return null;
    }

    /**
     * Retreive the temporary url of a conversion or nested conversion
     * Ex: $media->getTemporaryUrl('poster.480p', now()->addHour())
     *
     * @param  null|bool|string|array<int, string>  $fallback
     */
    public function getTemporaryUrl(
        \DateTimeInterface $expiration,
        ?string $conversion = null,
        array $options = [],
        null|bool|string|array $fallback = null,
        ?array $parameters = null,
    ): ?string {

        $url = null;

        if ($conversion) {
            $url = $this->getGeneratedConversion($conversion)?->getTemporaryUrl($expiration, $options);
        } elseif ($this->path) {
            /** @var null|string $url */
            $url = $this->getDisk()?->temporaryUrl($this->path, $expiration, $options);
        }

        if ($url) {

            if (! empty($parameters)) {
                return $url.'?'.http_build_query($parameters);
            }

            return $url;
        } elseif ($fallback === true) {
            return $this->getTemporaryUrl(
                expiration: $expiration,
                options: $options,
                parameters: $parameters,
            );
        } elseif (is_string($fallback)) {
            return $this->getTemporaryUrl(
                expiration: $expiration,
                conversion: $fallback,
                options: $options,
                parameters: $parameters
            );
        } elseif (is_array($fallback)) {
            return $this->getTemporaryUrl(
                expiration: $expiration,
                conversion: array_shift($fallback),
                options: $options,
                fallback: $fallback,
                parameters: $parameters
            );
        }

        return null;
    }

    public function getWidth(
        ?string $conversion = null,
        null|bool|string|array|int $fallback = null,
    ): ?int {
        $width = null;

        if ($conversion) {
            $width = $this->getGeneratedConversion($conversion)?->width;
        } else {
            $width = $this->width;
        }

        if ($width) {
            return $width;
        } elseif ($fallback === true) {
            return $this->getWidth();
        } elseif (is_string($fallback)) {
            return $this->getWidth(
                conversion: $fallback,
            );
        } elseif (is_array($fallback)) {
            return $this->getWidth(
                conversion: array_shift($fallback),
                fallback: $fallback,
            );
        } elseif (is_int($fallback)) {
            return $fallback;
        }

        return null;
    }

    public function getHeight(
        ?string $conversion = null,
        null|bool|string|array|int $fallback = null,
    ): ?int {
        $height = null;

        if ($conversion) {
            $height = $this->getGeneratedConversion($conversion)?->height;
        } else {
            $height = $this->height;
        }

        if ($height) {
            return $height;
        } elseif ($fallback === true) {
            return $this->getHeight();
        } elseif (is_string($fallback)) {
            return $this->getHeight(
                conversion: $fallback,
            );
        } elseif (is_array($fallback)) {
            return $this->getHeight(
                conversion: array_shift($fallback),
                fallback: $fallback,
            );
        } elseif (is_int($fallback)) {
            return $fallback;
        }

        return null;
    }

    public function getName(
        ?string $conversion = null,
        null|bool|string|array $fallback = null,
    ): ?string {
        $name = null;

        if ($conversion) {
            $name = $this->getGeneratedConversion($conversion)?->name;
        } else {
            $name = $this->name;
        }

        if ($name) {
            return $name;
        } elseif ($fallback === true) {
            return $this->getName();
        } elseif (is_string($fallback)) {
            return $this->getName(
                conversion: $fallback,
            ) ?? $fallback;
        } elseif (is_array($fallback)) {
            return $this->getName(
                conversion: array_shift($fallback),
                fallback: $fallback,
            );
        }

        return null;
    }

    public function getFileName(
        ?string $conversion = null,
        null|bool|string|array $fallback = null,
    ): ?string {
        $fileName = null;

        if ($conversion) {
            $fileName = $this->getGeneratedConversion($conversion)?->file_name;
        } else {
            $fileName = $this->file_name;
        }

        if ($fileName) {
            return $fileName;
        } elseif ($fallback === true) {
            return $this->getFileName();
        } elseif (is_string($fallback)) {
            return $this->getFileName(
                conversion: $fallback,
            ) ?? $fallback;
        } elseif (is_array($fallback)) {
            return $this->getFileName(
                conversion: array_shift($fallback),
                fallback: $fallback,
            );
        }

        return null;
    }

    public function getSize(
        ?string $conversion = null,
        null|bool|string|array|int $fallback = null,
    ): ?int {
        $size = null;

        if ($conversion) {
            $size = $this->getGeneratedConversion($conversion)?->size;
        } else {
            $size = $this->size;
        }

        if ($size) {
            return $size;
        } elseif ($fallback === true) {
            return $this->getSize();
        } elseif (is_string($fallback)) {
            return $this->getSize(
                conversion: $fallback,
            );
        } elseif (is_array($fallback)) {
            return $this->getSize(
                conversion: array_shift($fallback),
                fallback: $fallback,
            );
        } elseif (is_int($fallback)) {
            return $fallback;
        }

        return null;
    }

    public function getAspectRatio(
        ?string $conversion = null,
        null|bool|string|array|float $fallback = null,
    ): ?float {
        $aspectRatio = null;

        if ($conversion) {
            $aspectRatio = $this->getGeneratedConversion($conversion)?->aspect_ratio;
        } else {
            $aspectRatio = $this->aspect_ratio;
        }

        if ($aspectRatio) {
            return $aspectRatio;
        } elseif ($fallback === true) {
            return $this->getAspectRatio();
        } elseif (is_string($fallback)) {
            return $this->getAspectRatio(
                conversion: $fallback,
            );
        } elseif (is_array($fallback)) {
            return $this->getAspectRatio(
                conversion: array_shift($fallback),
                fallback: $fallback,
            );
        } elseif (is_float($fallback)) {
            return $fallback;
        }

        return null;
    }

    public function getMimeType(
        ?string $conversion = null,
        null|bool|string|array $fallback = null,
    ): ?string {
        $mimeType = null;

        if ($conversion) {
            $mimeType = $this->getGeneratedConversion($conversion)?->mime_type;
        } else {
            $mimeType = $this->mime_type;
        }

        if ($mimeType) {
            return $mimeType;
        } elseif ($fallback === true) {
            return $this->getMimeType();
        } elseif (is_string($fallback)) {
            return $this->getMimeType(
                conversion: $fallback,
            ) ?? $fallback;
        } elseif (is_array($fallback)) {
            return $this->getMimeType(
                conversion: array_shift($fallback),
                fallback: $fallback,
            );
        }

        return null;
    }

    // End attributes getters ----------------------------------------------------------------------
}
