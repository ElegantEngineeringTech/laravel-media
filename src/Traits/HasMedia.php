<?php

namespace Elegantly\Media\Traits;

use Elegantly\Media\Casts\GeneratedConversion;
use Elegantly\Media\MediaCollection;
use Elegantly\Media\MediaConversion;
use Elegantly\Media\Models\Media;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * @template TMedia of Media
 *
 * @property ?string $uuid
 * @property EloquentCollection<int, TMedia> $media ordered by order_column
 */
trait HasMedia
{
    public static function bootHasMedia()
    {
        static::deleting(function (Model $model) {

            if (! config('media.delete_media_with_model')) {
                return true;
            }

            $isSoftDeleting = method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting();

            if (
                $isSoftDeleting && ! config('media.delete_media_with_trashed_model')
            ) {
                return true;
            }

            $job = config('media.delete_media_with_model_job');

            foreach ($model->media as $media) {
                dispatch(new $job($media));
            }
        });
    }

    public function media(): MorphMany
    {
        return $this->morphMany(config('media.model'), 'model')
            ->orderByRaw('-order_column DESC')
            ->orderBy('id', 'asc');
    }

    /**
     * @return EloquentCollection<int, TMedia>
     */
    public function getMedia(?string $collection_name = null, ?string $collection_group = null): EloquentCollection
    {
        return $this->media
            ->when($collection_name, fn (EloquentCollection $collection) => $collection->where('collection_name', $collection_name))
            ->when($collection_group, fn (EloquentCollection $collection) => $collection->where('collection_group', $collection_group))
            ->values();
    }

    public function hasMedia(?string $collection_name = null, ?string $collection_group = null): bool
    {
        return $this->getMedia($collection_name, $collection_group)->isNotEmpty();
    }

    /**
     * @return TMedia
     */
    public function getFirstMedia(
        ?string $collection_name = null,
        ?string $collection_group = null
    ) {
        return $this->getMedia($collection_name, $collection_group)->first();
    }

    /**
     * @param  null|bool|string|array<int, string>  $fallback
     */
    public function getFirstMediaUrl(
        ?string $collection_name = null,
        ?string $collection_group = null,
        ?string $conversion = null,
        null|bool|string|array $fallback = null,
        ?array $parameters = null,
    ): ?string {
        $media = $this->getFirstMedia($collection_name, $collection_group);

        if ($media) {
            return $media->getUrl(
                conversion: $conversion,
                fallback: $fallback,
                parameters: $parameters
            );
        }

        $collection = $this->getMediaCollection($collection_name);

        return value($collection?->fallback);
    }

    /**
     * @return Arrayable<MediaCollection>|iterable<MediaCollection>|null
     */
    public function registerMediaCollections(): Arrayable|iterable|null
    {
        return [];
    }

    /**
     * @param  TMedia  $media
     * @return Arrayable<MediaConversion>|iterable<MediaConversion>|null
     */
    public function registerMediaConversions($media): Arrayable|iterable|null
    {
        return [];
    }

    /**
     * @param  TMedia  $media
     */
    public function registerMediaTransformations($media, UploadedFile|File $file): UploadedFile|File
    {
        return $file;
    }

    /**
     * @return Collection<string, MediaCollection>
     */
    public function getMediaCollections(): Collection
    {
        return collect($this->registerMediaCollections())
            ->push(new MediaCollection(
                name: config('media.default_collection_name'),
                single: false,
                public: false
            ))
            ->keyBy('name');
    }

    public function getMediaCollection(string $collectionName): ?MediaCollection
    {
        return $this->getMediaCollections()->get($collectionName);
    }

    public function hasMediaCollection(string $collectionName): bool
    {
        return (bool) $this->getMediaCollection($collectionName);
    }

    /**
     * @param  TMedia  $media
     * @return Collection<string, MediaConversion>
     */
    public function getMediaConversions($media): Collection
    {
        return collect($this->registerMediaConversions($media))->keyBy('conversionName');
    }

    public function getMediaConversionKey(string $conversion): string
    {
        return str_replace('.', '.conversions.', $conversion);
    }

    /**
     * @param  TMedia  $media
     */
    public function getMediaConversion($media, string $conversion): ?MediaConversion
    {
        $conversionsNames = explode('.', $conversion);

        $conversions = $this->getMediaConversions($media);

        return $this->getNestedMediaConversion(
            $media,
            $conversions->get($conversionsNames[0]),
            array_slice($conversionsNames, 1),
        );
    }

    /**
     * @param  TMedia  $media
     * @param  string[]  $conversionsNames
     */
    protected function getNestedMediaConversion(
        $media,
        ?MediaConversion $mediaConversion,
        array $conversionsNames,
    ): ?MediaConversion {

        if (empty($conversionsNames) || ! $mediaConversion) {
            return $mediaConversion;
        }

        $conversionName = $conversionsNames[0];

        $conversions = $mediaConversion->getConversions($media);

        return $this->getNestedMediaConversion(
            $media,
            $conversions->get($conversionName),
            array_slice($conversionsNames, 1),
        );
    }

    /**
     * @param  int[]  $except  Array of Media Ids
     * @return Collection<int, TMedia> The deleted media list
     */
    public function clearMediaCollection(
        string $collection_name,
        ?string $collection_group = null,
        array $except = []
    ): Collection {
        $media = $this->getMedia($collection_name, $collection_group)
            ->except($except)
            ->each(function (Media $model) {
                $model->delete();
            });

        $this->setRelation(
            'media',
            $this->media->except($media->modelKeys())
        );

        return $media;
    }

    /**
     * @return ?TMedia
     */
    public function deleteMedia(int $mediaId)
    {
        $media = $this->media->find($mediaId);

        if (! $media) {
            return null;
        }

        $media->delete();

        $this->setRelation(
            'media',
            $this->media->except([$mediaId])
        );

        return $media;
    }

    /**
     * @param  string|UploadedFile|resource  $file
     * @return TMedia
     */
    public function addMedia(
        mixed $file,
        ?string $collection_name = null,
        ?string $collection_group = null,
        ?string $disk = null,
        ?string $name = null,
        ?string $order = null,
        ?array $metadata = null,
    ) {
        $collection_name ??= config('media.default_collection_name');

        $collection = $this->getMediaCollection($collection_name);

        if (! $collection) {
            $class = static::class;
            throw new Exception("[Media collection not registered] {$collection_name} is not registered for the model {$class}.");
        }

        $model = config('media.model');
        /** @var TMedia $media */
        $media = new $model;

        $media->model()->associate($this);

        $media->collection_group = $collection_group;
        $media->order_column = $order;
        $media->metadata = $metadata;

        $media->storeFile(
            file: $file,
            collection_name: $collection_name,
            name: $name,
            disk: $disk ?? $collection->disk
        );

        if ($this->relationLoaded('media')) {
            $this->setRelation(
                'media',
                $this->media->push($media->withoutRelations())
            );
        }

        if ($collection->single) {
            $this->clearMediaCollection($collection_name, except: [$media->id]);
        }

        $this->dispatchConversions($media);

        return $media;
    }

    /**
     * @param  TMedia  $media
     */
    public function dispatchConversion($media, string $conversionName): static
    {
        $conversion = $this->getMediaConversion($media, $conversionName);

        if (! $conversion) {
            return $this;
        }

        $media->deleteGeneratedConversion($conversion->conversionName);

        $media
            ->putGeneratedConversion($conversion->conversionName, new GeneratedConversion(state: 'pending'))
            ->save();

        $conversion->dispatch();

        return $this;
    }

    /**
     * Dispatch media conversions for a specific media collection
     *
     * @param  bool  $sync  Overrides Conversion sync attribute
     */
    public function dispatchCollectionConversions(
        string $collectionName,
        ?bool $force = false,
        ?array $only = null,
        ?array $except = null,
        ?bool $sync = null,
    ): static {

        foreach ($this->getMedia($collectionName) as $media) {
            $this->dispatchConversions(
                media: $media,
                force: $force,
                only: $only,
                except: $except,
                sync: $sync,
            );
        }

        return $this;
    }

    /**
     * @param  TMedia  $media
     * @param  bool  $sync  Overrides Conversion sync attribute
     */
    public function dispatchConversions(
        $media,
        ?bool $force = false,
        ?array $only = null,
        ?array $except = null,
        ?bool $sync = null,
    ): static {
        $conversions = $this->getMediaConversions($media)
            ->only($only)
            ->except($except);

        if (! $force) {
            $conversions = $conversions->filter(function (MediaConversion $conversion) use ($media) {
                return ! $media->hasGeneratedConversion($conversion->conversionName);
            });
        }

        if ($conversions->isEmpty()) {
            return $this;
        }

        foreach ($conversions as $conversion) {
            $media->deleteGeneratedConversionFiles($conversion->conversionName);
            $media->putGeneratedConversion($conversion->conversionName, new GeneratedConversion(state: 'pending'));
        }

        $media->save();

        foreach ($conversions as $conversion) {
            $conversion->dispatch(
                sync: $sync,
            );
        }

        return $this;
    }
}
