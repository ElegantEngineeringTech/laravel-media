<?php

namespace Finller\LaravelMedia\Traits;

use Finller\LaravelMedia\Jobs\ConversionJob;
use Finller\LaravelMedia\Media;
use Finller\LaravelMedia\MediaCollection;
use Finller\LaravelMedia\MediaConversion;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * @property ?string $uuid
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
        return $this->media->where('collection_name', $collection_name);
    }

    /**
     * @return Collection<string, MediaCollection>
     */
    public function getMediaCollections(): Collection
    {
        return collect([]);
    }

    /**
     * @return Collection<string, MediaConversion>
     */
    public function getMediaConversions(Media $media): Collection
    {
        $conversions = collect([]);

        return $conversions;
    }

    public function saveMedia(string|UploadedFile $file, string $collection_name = null, string $name = null, string $disk = null): static
    {
        $collection_name ??= config('media.default_collection_name');

        $media = new Media();

        $media->model()->associate($this);

        $media->storeFile(
            file: $file,
            collection_name: $collection_name,
            name: $name,
            disk: $disk
        );

        $this->dispatchConversions($media, $collection_name);

        return $this;
    }

    public function dispatchConversions(Media $media): static
    {
        $conversions = $this->getMediaConversions($media);

        if ($conversions->isEmpty()) {
            return $this;
        }

        foreach ($conversions as $name => $conversion) {
            if ($conversion->job instanceof ConversionJob) {
                dispatch($conversion->job);
            } else {
                $job = $conversion->job;
                dispatch(new $job($media, $name));
            }
        }

        return $this;
    }
}
