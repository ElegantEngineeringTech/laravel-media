<?php

namespace Finller\LaravelMedia\Traits;

use Finller\LaravelMedia\Casts\GeneratedConversion;
use Finller\LaravelMedia\Media;
use Finller\LaravelMedia\MediaCollection;
use Finller\LaravelMedia\MediaConversion;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * @property ?string $uuid
 * @property EloquentCollection<int, Media> $media
 */
trait HasMedia
{
    public function media(): MorphMany
    {
        return $this->morphMany(config('media.model'), 'model');
    }

    /**
     * @return EloquentCollection<int, Media>
     */
    public function getMedia(string $collection_name = null): EloquentCollection
    {
        return $this->media->where('collection_name', $collection_name ?? config('media.default_collection_name'));
    }

    /**
     * @return Arrayable<MediaCollection>|iterable<MediaCollection>|null
     */
    protected function registerMediaCollections(): Arrayable|iterable|null
    {
        return collect();
    }

    /**
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

    /**
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

    public function getMediaConversion(Media $media, string $conversion): ?MediaConversion
    {
        return data_get($this->getMediaConversions($media), $this->getMediaConversionKey($conversion));
    }

    public function hasMediaCollection(string $collection_name): bool
    {
        return $this->getMediaCollections()->has($collection_name);
    }

    /**
     * @param  int[]  $except
     */
    public function clearMediaCollection(string $collection_name, array $except = []): static
    {
        $this->getMedia($collection_name)
            ->except($except)
            ->each(function (Media $media) {
                $media->delete();
            });

        return $this;
    }

    public function addMedia(string|UploadedFile $file, string $collection_name = null, string $name = null, string $disk = null): Media
    {
        $collection_name ??= config('media.default_collection_name');

        $collection = $this->getMediaCollections()->get($collection_name);

        if (! $collection) {
            $class = static::class;
            throw new Exception("The media collection {$collection_name} is not registered for {$class}");
        }

        $media = new Media();

        $media->model()->associate($this);

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
            dispatch($conversion->job);
        }

        return $this;
    }
}
