<?php

namespace Finller\LaravelMedia\Traits;

use Finller\LaravelMedia\Jobs\ConversionJob;
use Finller\LaravelMedia\Media;
use Finller\LaravelMedia\MediaCollection;
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
        return $this->morphMany(config('media-library.media_model'), 'model');
    }

    /**
     * @return Collection<string, MediaCollection>
     */
    public function getMediaCollections(): Collection
    {
        return collect([]);
    }

    public function addMedia(string|UploadedFile $file, string $collection_name = null): static
    {
        $collection_name ??= config('media.default_collection_name');

        $media = new Media();

        $media->storeFile($file);

        $this->dispatchConversions($media, $collection_name);

        return $this;
    }

    public function dispatchConversions(Media $media, string $collection_name): static
    {
        $collection = $this->getMediaCollections()->get($collection_name);

        if (! $collection) {
            return $this;
        }

        foreach ($collection->conversions as $conversion) {
            if ($conversion->job instanceof ConversionJob) {
                dispatch($conversion->job);
            } else {
                $job = $conversion->job;
                dispatch(new $job($media, $conversion->name));
            }
        }

        return $this;
    }
}
