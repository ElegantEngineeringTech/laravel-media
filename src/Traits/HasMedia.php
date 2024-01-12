<?php

namespace Finller\Media\Traits;

use Finller\Media\Casts\GeneratedConversion;
use Finller\Media\MediaCollection;
use Finller\Media\MediaConversion;
use Finller\Media\Models\Media;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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

    /**c
     * @return TMedia
     */
    public function getFirstMedia(?string $collection_name = null, ?string $collection_group = null): ?Media
    {
        return $this->getMedia($collection_name, $collection_group)->first();
    }

    public function getFirstMediaUrl(
        ?string $collection_name = null,
        ?string $collection_group = null,
        ?string $conversion = null,
    ): ?string {
        $media = $this->getFirstMedia($collection_name, $collection_group);

        if ($media) {
            return $media->getUrl($conversion);
        }

        $collection = $this->getMediaCollection($collection_name);

        return value($collection?->fallback);

    }

    /**
     * @return Arrayable<MediaCollection>|iterable<MediaCollection>|null
     */
    protected function registerMediaCollections(): Arrayable|iterable|null
    {
        return collect();
    }

    /**
     * @param  TMedia  $media
     * @return Arrayable<MediaConversion>|iterable<MediaConversion>|null
     */
    protected function registerMediaConversions(Media $media): Arrayable|iterable|null
    {
        return collect();
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

    public function hasMediaCollection(string $collection_name): bool
    {
        return $this->getMediaCollections()->has($collection_name);
    }

    public function getMediaCollection(string $collection_name): ?MediaCollection
    {
        return $this->getMediaCollections()->get($collection_name);
    }

    /**
     * @param  TMedia  $media
     * @return Collection<string, MediaConversion>
     */
    public function getMediaConversions(Media $media): Collection
    {
        return collect($this->registerMediaConversions($media))->keyBy('name');
    }

    public function getMediaConversionKey(string $conversion): string
    {
        return str_replace('.', '.conversions.', $conversion);
    }

    /**
     * @param  TMedia  $media
     */
    public function getMediaConversion(Media $media, string $conversion): ?MediaConversion
    {
        return data_get($this->getMediaConversions($media), $this->getMediaConversionKey($conversion));
    }

    /**
     * @param  int[]  $except Array of Media Ids
     * @return Collection<int, TMedia> The deleted media list
     */
    public function clearMediaCollection(string $collection_name, array $except = []): Collection
    {
        $media = $this->getMedia($collection_name)
            ->except($except)
            ->each(function (Media $media) {
                $media->delete();
            });

        return $media;
    }

    /**
     * @return TMedia
     */
    public function addMedia(
        string|UploadedFile $file,
        ?string $collection_name = null,
        ?string $collection_group = null,
        ?string $disk = null,
        ?string $name = null,
        ?string $order = null,
        ?array $metadata = null,
    ): Media {
        $collection_name ??= config('media.default_collection_name');

        $collection = $this->getMediaCollection($collection_name);

        if (! $collection) {
            $class = static::class;
            throw new Exception("The media collection {$collection_name} is not registered for the model {$class}.");
        }

        $model = config('media.model');
        /** @var TMedia $media */
        $media = new $model();

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

        if ($collection->single) {
            $this->clearMediaCollection($collection_name, except: [$media->id]);
        }

        $this->dispatchConversions($media);

        return $media;
    }

    /**
     * @param  TMedia  $media
     */
    public function dispatchConversion(Media $media, string $conversionName): static
    {
        $conversion = $this->getMediaConversion($media, $conversionName);

        if (! $conversion) {
            return $this;
        }

        $media->deleteGeneratedConversion($conversion->name);

        $media
            ->putGeneratedConversion($conversion->name, new GeneratedConversion(state: 'pending'))
            ->save();

        dispatch($conversion->job);

        return $this;
    }

    /**
     * @param  TMedia  $media
     */
    public function dispatchConversions(Media $media): static
    {
        $conversions = $this->getMediaConversions($media);

        if ($conversions->isEmpty()) {
            return $this;
        }

        $media->deleteGeneratedConversions();

        foreach ($conversions as $conversion) {
            $media->putGeneratedConversion($conversion->name, new GeneratedConversion(state: 'pending'));
        }

        $media->save();

        foreach ($conversions as $conversion) {
            if ($conversion->sync) {
                dispatch_sync($conversion->job);
            } else {
                dispatch($conversion->job);
            }
        }

        return $this;
    }
}
