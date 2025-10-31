<?php

declare(strict_types=1);

namespace Elegantly\Media\Models;

use Carbon\Carbon;
use Closure;
use Elegantly\Media\Concerns\InteractWithFiles;
use Elegantly\Media\Contracts\InteractWithMedia;
use Elegantly\Media\Database\Factories\MediaFactory;
use Elegantly\Media\Enums\MediaConversionState;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Events\MediaConversionAddedEvent;
use Elegantly\Media\Events\MediaFileStoredEvent;
use Elegantly\Media\FileDownloaders\HttpFileDownloader;
use Elegantly\Media\Helpers\File;
use Elegantly\Media\MediaConversionDefinition;
use Elegantly\Media\PathGenerators\AbstractPathGenerator;
use Elegantly\Media\TemporaryDirectory;
use Elegantly\Media\Traits\HasUuid;
use Elegantly\Media\UrlFormatters\AbstractUrlFormatter;
use Exception;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Http\File as HttpFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $collection_name
 * @property ?string $collection_group
 * @property ?string $average_color
 * @property ?int $order_column
 * @property ?array<array-key, mixed> $metadata
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
    use InteractWithFiles {
        getUrl as traitGetUrl;
        getTemporaryUrl as traitGetTemporaryUrl;
    }

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    protected $appends = ['url'];

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'type' => MediaType::class,
            'metadata' => 'array',
            'duration' => 'float',
            'aspect_ratio' => 'float',
        ];
    }

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
        /** @var class-string<MediaConversion> */
        $mediaConversionModel = config()->string('media.media_conversion_model');

        return $this->hasMany($mediaConversionModel)->chaperone();
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

        if (! is_string($file) || filter_var($file, FILTER_VALIDATE_URL)) {
            /** @var static $value */
            $value = TemporaryDirectory::callback(function ($temporaryDirectory) use ($file, $destination, $name, $disk, $before) {

                $path = HttpFileDownloader::download(
                    file: $file,
                    destination: $temporaryDirectory->path()
                );

                return $this->storeFileFromHttpFile(new HttpFile($path), $destination, $name, $disk, $before);
            });

            return $value;
        }

        return $this->storeFileFromHttpFile(new HttpFile($file), $destination, $name, $disk, $before);
    }

    /**
     * @param  null|(Closure(UploadedFile|HttpFile $file, \Spatie\TemporaryDirectory\TemporaryDirectory $temporaryDirectory):(UploadedFile|HttpFile))  $before
     */
    public function storeFileFromHttpFile(
        UploadedFile|HttpFile $file,
        ?string $destination = null,
        ?string $name = null,
        ?string $disk = null,
        ?Closure $before = null,
    ): static {

        /** @var class-string<AbstractPathGenerator> */
        $pathGenerator = config('media.default_path_generator');

        $destination ??= (new $pathGenerator)->media($this)->value();
        $name ??= File::name($file) ?? Str::random(6);
        $disk ??= config()->string('media.disk', config()->string('filesystems.default', 'local'));

        TemporaryDirectory::callback(function ($temporaryDirectory) use ($file, $destination, $name, $disk, $before) {
            if ($before) {
                $file = $before($file, $temporaryDirectory);
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

        });

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
     */
    public function dispatchConversion(
        string $conversion,
        bool $force = true,
    ): ?PendingDispatch {
        if (
            $force === false &&
            $this->hasConversion($conversion, MediaConversionState::Succeeded)
        ) {
            return null;
        }

        if ($definition = $this->getConversionDefinition($conversion)) {

            $converter = ($definition->converter)($this);

            $job = dispatch($converter->conversion($conversion));

            if ($queue = $definition->queue) {
                $job->onQueue($queue);
            }

            return $job;

        }

        return null;
    }

    /**
     * Execute any conversion while generating missing parents.
     */
    public function executeConversion(
        string $conversion,
        bool $force = true,
        bool $withChildren = true,
    ): ?MediaConversion {

        if (
            $force === false &&
            $this->hasConversion($conversion, MediaConversionState::Succeeded)
        ) {
            return null;
        }

        if ($definition = $this->getConversionDefinition($conversion)) {

            $converter = ($definition->converter)($this);

            return $converter
                ->conversion($conversion)
                ->withChildren($withChildren)
                ->handle();
        }

        return null;
    }

    public function getOrExecuteConversion(
        string $name,
        bool $withChildren = true,
    ): ?MediaConversion {
        if ($conversion = $this->getConversion($name, MediaConversionState::Succeeded)) {
            return $conversion;
        }

        return $this->executeConversion($name, withChildren: $withChildren);
    }

    /**
     * @param  null|string|string[]  $fallback
     * @param  null|MediaConversionState|MediaConversionState[]  $state
     */
    public function getConversion(
        string $name,
        null|MediaConversionState|array $state = null,
        null|string|array $fallback = null
    ): ?MediaConversion {

        $state = Arr::wrap($state);

        $conversion = $this->conversions->firstWhere(function ($mediaConversion) use ($name, $state) {
            if ($state && ! in_array($mediaConversion->state, $state)) {
                return false;
            }

            return $mediaConversion->conversion_name === $name;
        });

        if ($conversion) {
            return $conversion;
        }

        if (is_string($fallback)) {
            return $this->getConversion($fallback, $state);
        }

        if (is_array($fallback) && $firstFallback = array_shift($fallback)) {
            return $this->getConversion(
                name: $firstFallback,
                state: $state,
                fallback: $fallback
            );
        }

        return null;
    }

    public function hasConversion(string $name, ?MediaConversionState $state = null): bool
    {
        return (bool) $this->getConversion($name, $state);
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

    public function replaceConversion(MediaConversion $conversion): MediaConversion
    {
        $existingConversion = $this->getConversion($conversion->conversion_name);

        if ($existingConversion?->is($conversion)) {
            return $conversion;
        }

        if ($existingConversion) {
            $existingConversion->delete();
            $this->setRelation(
                'conversions',
                $this->conversions->except([$existingConversion->id])
            );
        }

        $this->conversions()->save($conversion);
        $this->conversions->push($conversion);

        return $conversion;
    }

    /**
     * Store a file as a conversion and dispatch children conversions
     *
     * @param  string|resource|UploadedFile|HttpFile  $file
     * @param  array<array-key, mixed>  $metadata
     * @param  array<array-key, mixed>  $attributes
     */
    public function addConversion(
        $file,
        string $conversionName,
        ?MediaConversion $parent = null,
        ?string $name = null,
        ?string $destination = null,
        ?string $disk = null,
        ?array $metadata = null,
        array $attributes = [],
        bool $deleteChildren = false
    ): MediaConversion {

        /** @var class-string<AbstractPathGenerator> */
        $pathGenerator = config('media.default_path_generator');

        /**
         * Prefix name with parent if not already done
         */
        if ($parent && ! str_contains($conversionName, '.')) {
            $conversionName = "{$parent->conversion_name}.{$conversionName}";
        }

        if ($existingConversion = $this->getConversion($conversionName)) {
            $existingConversion->delete();
            $this->setRelation(
                'conversions',
                $this->conversions->except([$existingConversion->id])
            );
        }

        /** @var class-string<MediaConversion> */
        $mediaConversionModel = config()->string('media.media_conversion_model');

        $conversion = new $mediaConversionModel;

        $conversion->media_id = $this->id;
        $conversion->conversion_name = $conversionName;
        $conversion->state = MediaConversionState::Succeeded;

        $conversion->metadata = $metadata;
        $conversion->fill($attributes);

        $conversion->storeFile(
            file: $file,
            destination: $destination ?? (new $pathGenerator)->conversion($this, $conversion)->value(),
            name: $name,
            disk: $disk ?? $this->disk
        );

        $this->conversions->push($conversion);

        if ($deleteChildren) {
            $this->deleteChildrenConversions($conversionName);
        }

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

                $job = $this->dispatchConversion(
                    conversion: $conversion,
                    force: $force,
                );

                if ($definition->delay !== null) {
                    $job?->delay($definition->delay);
                }

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
            $path = $this->getConversion($conversion, MediaConversionState::Succeeded)?->path;
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
     * @param  null|bool|string|array<int, string|bool>  $fallback
     * @param  null|array<array-key, mixed>  $parameters
     * @param  null|class-string<AbstractUrlFormatter>  $formatter
     * @param  bool  $dispatch  Dispatch not found conversion
     */
    public function getUrl(
        ?string $conversion = null,
        null|bool|string|array $fallback = null,
        ?array $parameters = null,
        ?string $formatter = null,
        bool $dispatch = false
    ): ?string {

        $url = null;

        if ($conversion) {
            $mediaConversion = $this->getConversion($conversion);

            if ($mediaConversion && $mediaConversion->state === MediaConversionState::Succeeded) {
                $url = $mediaConversion->getUrl(
                    parameters: $parameters,
                    formatter: $formatter,
                );
            } elseif ($mediaConversion === null && $dispatch) {
                $this->dispatchConversion($conversion, false);
            }

        } elseif ($this->path) {
            /** @var null|string $url */
            $url = $this->traitGetUrl(
                parameters: $parameters,
                formatter: $formatter
            );
        }

        if ($url) {
            return $url;
        } elseif ($fallback === true) {
            return $this->getUrl(
                parameters: $parameters,
                formatter: $formatter,
            );
        } elseif (is_string($fallback)) {
            return $this->getUrl(
                conversion: $fallback,
                parameters: $parameters,
                formatter: $formatter,
            ) ?? $fallback;
        } elseif (is_array($fallback)) {
            $fallbackValue = array_shift($fallback);

            if ($fallbackValue === true) {
                return $this->getUrl(
                    parameters: $parameters,
                    formatter: $formatter,
                );
            } elseif (is_string($fallbackValue)) {
                return $this->getUrl(
                    conversion: $fallbackValue,
                    fallback: $fallback,
                    parameters: $parameters,
                    formatter: $formatter,
                );
            }

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
     * @param  null|class-string<AbstractUrlFormatter>  $formatter
     */
    public function getTemporaryUrl(
        \DateTimeInterface $expiration,
        ?string $conversion = null,
        array $options = [],
        null|bool|string|array $fallback = null,
        ?array $parameters = null,
        ?string $formatter = null
    ): ?string {

        $url = null;

        if ($conversion) {
            $url = $this->getConversion($conversion, MediaConversionState::Succeeded)?->getTemporaryUrl($expiration, $options, $parameters, $formatter);
        } elseif ($this->path) {
            /** @var null|string $url */
            $url = $this->traitGetTemporaryUrl($expiration, $options, $parameters, $formatter);
        }

        if ($url) {
            return $url;
        } elseif ($fallback === true) {
            return $this->getTemporaryUrl(
                expiration: $expiration,
                options: $options,
                parameters: $parameters,
                formatter: $formatter,
            );
        } elseif (is_string($fallback)) {
            return $this->getTemporaryUrl(
                expiration: $expiration,
                conversion: $fallback,
                options: $options,
                parameters: $parameters,
                formatter: $formatter,
            );
        } elseif (is_array($fallback)) {
            return $this->getTemporaryUrl(
                expiration: $expiration,
                conversion: array_shift($fallback),
                options: $options,
                fallback: $fallback,
                parameters: $parameters,
                formatter: $formatter,
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
            $width = $this->getConversion($conversion, MediaConversionState::Succeeded)?->width;
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
            $height = $this->getConversion($conversion, MediaConversionState::Succeeded)?->height;
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
            $name = $this->getConversion($conversion, MediaConversionState::Succeeded)?->name;
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
            $fileName = $this->getConversion($conversion, MediaConversionState::Succeeded)?->file_name;
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
            $size = $this->getConversion($conversion, MediaConversionState::Succeeded)?->size;
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
            $aspectRatio = $this->getConversion($conversion, MediaConversionState::Succeeded)?->aspect_ratio;
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
            $mimeType = $this->getConversion($conversion, MediaConversionState::Succeeded)?->mime_type;
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
