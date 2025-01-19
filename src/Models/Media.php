<?php

declare(strict_types=1);

namespace Elegantly\Media\Models;

use Carbon\Carbon;
use Closure;
use Elegantly\Media\Concerns\InteractWithFiles;
use Elegantly\Media\Contracts\InteractWithMedia;
use Elegantly\Media\Database\Factories\MediaFactory;
use Elegantly\Media\Definitions\MediaConversionDefinition;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Events\MediaConversionAddedEvent;
use Elegantly\Media\Events\MediaFileStoredEvent;
use Elegantly\Media\FileDownloaders\FileDownloader;
use Elegantly\Media\Helpers\File;
use Elegantly\Media\Jobs\MediaConversionJob;
use Elegantly\Media\TemporaryDirectory;
use Elegantly\Media\Traits\HasUuid;
use Exception;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Http\File as HttpFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $collection_name
 * @property ?string $collection_group
 * @property ?string $average_color
 * @property ?int $order_column
 * @property ?ArrayObject<array-key, mixed> $metadata
 * @property ?InteractWithMedia<Media> $model
 * @property ?string $model_type
 * @property ?int $model_id
 * @property EloquentCollection<int, MediaConversion> $conversions
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ?string $url
 */
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
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
        'duration' => 'float',
        'aspect_ratio' => 'float',
    ];

    public static function booted()
    {
        static::deleting(function (Media $media) {

            $media->conversions->each(fn ($conversion) => $conversion->delete());

            $media->deleteFile();
        });
    }

    /**
     * @return Attribute<null|string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn () => $this->getUrl());
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<MediaConversion, $this>
     */
    public function conversions(): HasMany
    {
        return $this->hasMany(MediaConversion::class)->chaperone();
    }

    // Storing File ----------------------------------------------------------

    /**
     * @param  string|UploadedFile|HttpFile|resource  $file
     * @param  null|(Closure(UploadedFile|HttpFile $file):(UploadedFile|HttpFile))  $before
     */
    public function storeFile(
        mixed $file,
        ?string $destination = null,
        ?string $name = null,
        ?string $disk = null,
        ?Closure $before = null,
    ): static {
        if ($file instanceof UploadedFile || $file instanceof HttpFile) {
            return $this->storeFileFromHttpFile($file, $destination, $name, $disk, $before);
        }

        if (
            (is_string($file) && filter_var($file, FILTER_VALIDATE_URL)) ||
            ! is_string($file)
        ) {
            return TemporaryDirectory::callback(function ($temporaryDirectory) use ($file, $destination, $name, $disk, $before) {
                $path = FileDownloader::download(
                    file: $file,
                    destination: $temporaryDirectory->path()
                );

                return $this->storeFileFromHttpFile(new HttpFile($path), $destination, $name, $disk, $before);
            });
        }

        return $this->storeFileFromHttpFile(new HttpFile($file), $destination, $name, $disk, $before);
    }

    /**
     * @param  null|(Closure(UploadedFile|HttpFile $file):(UploadedFile|HttpFile))  $before
     */
    public function storeFileFromHttpFile(
        UploadedFile|HttpFile $file,
        ?string $destination = null,
        ?string $name = null,
        ?string $disk = null,
        ?Closure $before = null,
    ): static {

        $destination ??= $this->makeFreshPath();
        $name ??= File::name($file) ?? Str::random(6);
        $disk ??= $this->disk ?? config()->string('media.disk', config()->string('filesystems.default', 'local'));

        if ($before) {
            $file = $before($file);
        }

        $path = $this->putFile(
            disk: $disk,
            destination: $destination,
            file: $file,
            name: $name,
        );

        if (! $path) {
            throw new Exception("Storing Media File '{$file->getPath()}' to disk '{$disk}' at '{$destination}' failed.");
        }

        $this->save();

        event(new MediaFileStoredEvent($this));

        return $this;
    }

    // \ Storing File ----------------------------------------------------------

    // Managing Conversions ----------------------------------------------------------

    /**
     * @return MediaConversionDefinition[]
     */
    public function registerConversions(): array
    {
        return [];
    }

    /**
     * Retreive conversions defined in both the Media and the Model MediaCollection
     * Model's MediaCollection definitions override the Media's definitions
     *
     * @return array<string, MediaConversionDefinition>
     */
    public function getConversionsDefinitions(): array
    {
        $conversions = collect($this->registerConversions());

        if (
            $this->model &&
            $collection = $this->model->getMediaCollection($this->collection_name)
        ) {
            $conversions->push(...array_values($collection->conversions));
        }

        /** @var array<string, MediaConversionDefinition> */
        $value = $conversions->keyBy('name')->toArray();

        return $value;
    }

    public function getConversionDefinition(string $name): ?MediaConversionDefinition
    {
        /** @var ?MediaConversionDefinition $value */
        $value = data_get(
            target: $this->getConversionsDefinitions(),
            key: str_replace('.', '.conversions.', $name)
        );

        return $value;
    }

    /**
     * @return array<string, MediaConversionDefinition>
     */
    public function getChildrenConversionsDefinitions(string $name): array
    {
        return $this->getConversionDefinition($name)->conversions ?? [];
    }

    /**
     * Dispatch any conversion while generating missing parents.
     * Will check for 'shouldExecute' definition method.
     */
    public function dispatchConversion(
        string $conversion,
        bool $force = true,
    ): ?PendingDispatch {
        if (
            $force === false &&
            $this->hasConversion($conversion)
        ) {
            return null;
        }

        if ($definition = $this->getConversionDefinition($conversion)) {

            $job = new MediaConversionJob(
                media: $this,
                conversion: $conversion
            );

            return dispatch($job)->onQueue($definition->queue ?? $job->queue);

        }

        return null;
    }

    /**
     * Execute any conversion while generating missing parents.
     * Will check for 'shouldExecute' definition method.
     */
    public function executeConversion(
        string $conversion,
        bool $force = true,
    ): ?MediaConversion {

        if (
            $force === false &&
            $this->hasConversion($conversion)
        ) {
            return null;
        }

        if ($definition = $this->getConversionDefinition($conversion)) {

            if (str_contains($conversion, '.')) {
                $parent = $this->getOrExecuteConversion(
                    str($conversion)->beforeLast('.')->value()
                );
                /**
                 * Parent conversion can't be done, so children can't be executed either.
                 */
                if (! $parent) {
                    return null;
                }
            } else {
                $parent = null;
            }

            if (! $definition->shouldExecute($this, $parent)) {
                return null;
            }

            return $definition->execute($this, $parent);

        }

        return null;
    }

    public function getOrExecuteConversion(string $name): ?MediaConversion
    {
        if ($conversion = $this->getConversion($name)) {
            return $conversion;
        }

        return $this->executeConversion($name);
    }

    public function getConversion(string $name): ?MediaConversion
    {
        return $this->conversions->firstWhere('conversion_name', $name);
    }

    public function hasConversion(string $name): bool
    {
        return (bool) $this->getConversion($name);
    }

    public function getParentConversion(string $name): ?MediaConversion
    {
        if (! str_contains($name, '.')) {
            return null;
        }

        return $this->getConversion(
            str($name)->beforeLast('.')->value()
        );
    }

    /**
     * @return EloquentCollection<int, MediaConversion>
     */
    public function getChildrenConversions(string $name): EloquentCollection
    {
        return $this
            ->conversions
            ->filter(fn ($conversion) => str_starts_with($conversion->conversion_name, "{$name}."));
    }

    /**
     * Save a conversion and dispatch children conversions
     */
    public function replaceConversion(
        MediaConversion $conversion,
    ): MediaConversion {

        $existingConversion = $this->getConversion($conversion->conversion_name);

        if (
            $conversion->exists ||
            $conversion->is($existingConversion)
        ) {
            return $conversion;
        }

        $this->conversions()->save($conversion);
        $this->conversions->push($conversion);

        if ($existingConversion) {
            $existingConversion->delete();
            $this->setRelation(
                'conversions',
                $this->conversions->except([$existingConversion->id])
            );
        }

        $this->generateConversions(
            parent: $conversion,
            filter: fn ($definition) => $definition->immediate,
            force: true,
        );

        return $conversion;
    }

    /**
     * Store a file as a conversion and dispatch children conversions
     *
     * @param  string|resource|UploadedFile|HttpFile  $file
     */
    public function addConversion(
        $file,
        string $conversionName,
        ?MediaConversion $parent = null,
        ?string $name = null,
        ?string $destination = null,
        ?string $disk = null,
    ): MediaConversion {

        /**
         * Prefix name with parent if not already done
         */
        if ($parent && ! str_contains($conversionName, '.')) {
            $conversionName = "{$parent->conversion_name}.{$conversionName}";
        }

        /**
         * If the conversion already exists, we are going to overwrite it
         */
        $existingConversion = $this->getConversion($conversionName);

        /**
         * To delete old conversion files, we will use a untouched replicate
         */
        $existingConversionReplicate = $existingConversion?->replicate();

        $conversion = $existingConversion ?? new MediaConversion;

        $conversion->fill([
            'conversion_name' => $conversionName,
            'media_id' => $this->id,
            'state' => 'success',
            'state_set_at' => now(),
        ]);

        $conversion->storeFile(
            file: $file,
            destination: $destination ?? $this->makeFreshPath($conversionName),
            name: $name,
            disk: $disk ?? $this->disk
        );

        if ($existingConversionReplicate) {
            if (
                $existingConversionReplicate->path !== $conversion->path ||
                $existingConversionReplicate->disk !== $conversion->disk
            ) {
                $existingConversionReplicate->deleteFile();
            }
            /**
             * Because the conversion has been regenerated, its children are not up to date anymore
             */
            $this->deleteChildrenConversions($conversionName);
        } else {
            $this->conversions->push($conversion);
        }

        $this->generateConversions(
            parent: $conversion,
            filter: fn ($definition) => $definition->immediate,
            force: true,
        );

        event(new MediaConversionAddedEvent($conversion));

        return $conversion;
    }

    /**
     * Execute or dispatch first level conversions based on their definition
     *
     * @param  null|(Closure(MediaConversionDefinition $definition):bool)  $filter
     * @param  ?bool  $queued  force queueing the conversions
     * @return $this
     */
    public function generateConversions(
        ?MediaConversion $parent = null,
        ?Closure $filter = null,
        ?bool $queued = null,
        bool $force = false,
    ): static {
        if ($parent) {
            $definitions = $this->getChildrenConversionsDefinitions($parent->conversion_name);
        } else {
            $definitions = $this->getConversionsDefinitions();
        }

        foreach ($definitions as $definition) {

            if ($filter && ! $filter($definition)) {
                continue;
            }

            $conversion = $parent ? "{$parent->conversion_name}.{$definition->name}" : $definition->name;

            if ($queued ?? $definition->queued) {
                $this->dispatchConversion(
                    conversion: $conversion,
                    force: $force,
                );

            } else {
                // A failed conversion should not interrupt the process
                try {
                    $this->executeConversion(
                        conversion: $conversion,
                        force: $force
                    );
                } catch (\Throwable $th) {
                    report($th);
                }

            }
        }

        return $this;
    }

    /**
     * Delete Media Conversions and its derived conversions
     */
    public function deleteConversion(string $conversionName): static
    {
        $deleted = $this->conversions
            ->filter(function ($conversion) use ($conversionName) {
                if ($conversion->conversion_name === $conversionName) {
                    return true;
                }

                return str($conversion->conversion_name)->startsWith("{$conversionName}.");
            })
            ->each(fn ($conversion) => $conversion->delete());

        $this->setRelation(
            'conversions',
            $this->conversions->except($deleted->modelKeys())
        );

        return $this;
    }

    public function deleteChildrenConversions(string $conversionName): static
    {
        $deleted = $this
            ->getChildrenConversions($conversionName)
            ->each(fn ($conversion) => $conversion->delete());

        $this->setRelation(
            'conversions',
            $this->conversions->except($deleted->modelKeys())
        );

        return $this;
    }

    // \ Managing Conversions ----------------------------------------------------------

    /**
     * Generate the default base path for storing files
     * uuid/
     *  files
     *  /conversions
     *      /conversionName
     *      files
     */
    public function makeFreshPath(
        ?string $conversion = null,
        ?string $fileName = null
    ): string {
        /** @var string $prefix */
        $prefix = config('media.generated_path_prefix') ?? '';

        $root = Str::of($prefix)
            ->when($prefix, fn ($string) => $string->finish('/'))
            ->append($this->uuid)
            ->finish('/');

        if ($conversion) {
            return $root
                ->append('conversions/')
                ->append(str_replace('.', '/conversions/', $conversion))
                ->finish('/')
                ->append($fileName ?? '')
                ->value();
        }

        return $root->append($fileName ?? '')->value();
    }

    /**
     * @param  array<array-key, float|int|string>  $keys
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

    // Attributes Getters ----------------------------------------------------------------------

    /**
     * Retreive the path of a conversion or nested conversion
     * Ex: $media->getPath('poster.480p')
     *
     * @param  null|bool|string|array<int, string>  $fallback
     */
    public function getPath(
        ?string $conversion = null,
        null|bool|string|array $fallback = null,
    ): ?string {
        $path = null;

        if ($conversion) {
            $path = $this->getConversion($conversion)?->path;
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
     * @param  null|array<array-key, mixed>  $parameters
     */
    public function getUrl(
        ?string $conversion = null,
        null|bool|string|array $fallback = null,
        ?array $parameters = null,
    ): ?string {
        $url = null;

        if ($conversion) {
            $url = $this->getConversion($conversion)?->getUrl();
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
     * @param  array<array-key, mixed>  $options
     * @param  null|array<array-key, mixed>  $parameters
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
            $url = $this->getConversion($conversion)?->getTemporaryUrl($expiration, $options);
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

    /**
     * @param  null|bool|string|int|array<int, string>  $fallback
     */
    public function getWidth(
        ?string $conversion = null,
        null|bool|string|int|array $fallback = null,
    ): ?int {
        $width = null;

        if ($conversion) {
            $width = $this->getConversion($conversion)?->width;
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

    /**
     * @param  null|bool|string|int|array<int, string>  $fallback
     */
    public function getHeight(
        ?string $conversion = null,
        null|bool|string|int|array $fallback = null,
    ): ?int {
        $height = null;

        if ($conversion) {
            $height = $this->getConversion($conversion)?->height;
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

    /**
     * @param  null|bool|string|array<int, string>  $fallback
     */
    public function getName(
        ?string $conversion = null,
        null|bool|string|array $fallback = null,
    ): ?string {
        $name = null;

        if ($conversion) {
            $name = $this->getConversion($conversion)?->name;
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

    /**
     * @param  null|bool|string|array<int, string>  $fallback
     */
    public function getFileName(
        ?string $conversion = null,
        null|bool|string|array $fallback = null,
    ): ?string {
        $fileName = null;

        if ($conversion) {
            $fileName = $this->getConversion($conversion)?->file_name;
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

    /**
     * @param  null|bool|string|int|array<int, string>  $fallback
     */
    public function getSize(
        ?string $conversion = null,
        null|bool|string|int|array $fallback = null,
    ): ?int {
        $size = null;

        if ($conversion) {
            $size = $this->getConversion($conversion)?->size;
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

    /**
     * @param  null|bool|string|float|array<int, string>  $fallback
     */
    public function getAspectRatio(
        ?string $conversion = null,
        null|bool|string|float|array $fallback = null,
    ): ?float {
        $aspectRatio = null;

        if ($conversion) {
            $aspectRatio = $this->getConversion($conversion)?->aspect_ratio;
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

    /**
     * @param  null|bool|string|array<int, string>  $fallback
     */
    public function getMimeType(
        ?string $conversion = null,
        null|bool|string|array $fallback = null,
    ): ?string {
        $mimeType = null;

        if ($conversion) {
            $mimeType = $this->getConversion($conversion)?->mime_type;
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
