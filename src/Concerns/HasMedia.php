<?php

declare(strict_types=1);

namespace Elegantly\Media\Concerns;

use Elegantly\Media\Events\MediaAddedEvent;
use Elegantly\Media\Exceptions\InvalidMimeTypeException;
use Elegantly\Media\Helpers\File as HelpersFile;
use Elegantly\Media\Jobs\DeleteModelMediaJob;
use Elegantly\Media\MediaCollection;
use Elegantly\Media\Models\Media;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 *
 * @template TMedia of Media
 *
 * @property Collection<int, TMedia> $media
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
                $isSoftDeleting &&
                ! config('media.delete_media_with_trashed_model')
            ) {
                return true;
            }

            /** @var class-string<DeleteModelMediaJob> */
            $job = config('media.delete_media_with_model_job');

            $model->media->each(fn ($media) => dispatch(new $job($media)));

        });
    }

    /**
     * @return MorphMany<TMedia>
     */
    public function media(): MorphMany
    {
        return $this
            ->morphMany(config('media.model'), 'model')
            ->chaperone()
            ->orderByRaw('-order_column DESC')
            ->orderBy('id', 'asc');
    }

    /**
     * @return Arrayable<array-key, MediaCollection>|iterable<MediaCollection>|null
     */
    public function registerMediaCollections(): Arrayable|iterable|null
    {
        return [];
    }

    public function getMediaCollection(string $collectionName): ?MediaCollection
    {
        return collect($this->registerMediaCollections())->firstWhere('name', $collectionName);
    }

    /**
     * @return Collection<int, TMedia>
     */
    public function getMedia(
        ?string $collectionName = null,
        ?string $collectionGroup = null
    ): Collection {
        return $this->media
            ->when($collectionName, fn ($collection) => $collection->where('collection_name', $collectionName))
            ->when($collectionGroup, fn ($collection) => $collection->where('collection_group', $collectionGroup))
            ->values();
    }

    public function hasMedia(
        ?string $collectionName = null,
        ?string $collectionGroup = null
    ): bool {
        return $this->getMedia($collectionName, $collectionGroup)->isNotEmpty();
    }

    /**
     * @return TMedia
     */
    public function getFirstMedia(
        ?string $collectionName = null,
        ?string $collectionGroup = null
    ): ?Media {
        return $this->getMedia($collectionName, $collectionGroup)->first();
    }

    /**
     * @param  array<array-key, mixed>  $parameters
     */
    public function getFirstMediaUrl(
        ?string $collectionName = null,
        ?string $collectionGroup = null,
        ?string $conversion = null,
        ?array $parameters = null,
    ): ?string {
        $media = $this->getFirstMedia($collectionName, $collectionGroup);

        if ($url = $media?->getUrl(
            conversion: $conversion,
            parameters: $parameters
        )) {
            return $url;
        }

        if (
            $collectionName &&
            $collection = $this->getMediaCollection($collectionName)
        ) {
            return value($collection->fallback);
        }

        return null;
    }

    /**
     * @param  string|resource|UploadedFile|File  $file
     * @param  array<array-key, mixed>  $metadata
     * @param  array<array-key, mixed>  $attributes
     * @return TMedia
     */
    public function addMedia(
        mixed $file,
        ?string $collectionName = null,
        ?string $collectionGroup = null,
        ?string $name = null,
        ?string $disk = null,
        ?int $order = null,
        ?array $metadata = null,
        array $attributes = [],
    ): Media {
        $collectionName ??= config('media.default_collection_name');

        /** @var class-string<TMedia> */
        $model = config('media.model');

        $media = new $model;
        $media->fill($attributes);
        $media->model()->associate($this);
        $media->collection_name = $collectionName;
        $media->collection_group = $collectionGroup;
        $media->order_column = $order;
        $media->metadata = $metadata;

        $collection = $collectionName ? $this->getMediaCollection($collectionName) : null;

        $media->storeFile(
            file: $file,
            name: $name,
            disk: $disk ?? $collection?->disk,
            before: function ($file, $temporaryDirectory) use ($collection) {
                if ($acceptedMimeTypes = $collection?->acceptedMimeTypes) {
                    $mime = HelpersFile::mimeType($file);

                    if (! in_array($mime, $acceptedMimeTypes)) {
                        throw InvalidMimeTypeException::notAccepted(
                            $mime,
                            $acceptedMimeTypes
                        );
                    }
                }

                if ($transform = $collection?->transform) {
                    return $transform($file, $temporaryDirectory);
                }

                return $file;
            }
        );

        if ($this->relationLoaded('media')) {
            $this->media->push($media);
        }

        if ($collection?->single) {
            $this->clearMediaCollection(
                collectionName: $collectionName,
                except: [$media->id]
            );
        }

        $media->generateConversions(
            filter: fn ($definition) => $definition->immediate,
            force: true,
        );

        if ($onAdded = $collection?->onAdded) {
            $onAdded($media);
        }

        event(new MediaAddedEvent($media));

        return $media;
    }

    /**
     * @return $this
     */
    public function deleteMedia(int $mediaId): static
    {
        $this->media->find($mediaId)?->delete();

        $this->setRelation(
            'media',
            $this->media->except([$mediaId])
        );

        return $this;
    }

    /**
     * @return $this
     */
    public function clearMediaCollection(
        string $collectionName,
        ?string $collectionGroup = null,
        array $except = [],
    ): static {

        $media = $this->getMedia($collectionName, $collectionGroup)
            ->except($except)
            ->loadMissing(['conversions'])
            ->each(fn ($media) => $media->delete());

        $this->setRelation(
            'media',
            $this->media->except($media->modelKeys())
        );

        return $this;
    }

    /**
     * @return \Illuminate\Support\Collection<int, \Illuminate\Foundation\Bus\PendingDispatch>
     */
    public function dispatchMediaConversion(
        string $conversionName,
        bool $force = true,
        ?string $collectionName = null,
        ?string $collectionGroup = null,
    ): \Illuminate\Support\Collection {

        return $this
            ->getMedia($collectionName, $collectionGroup)
            ->toBase()
            ->map(function ($media) use ($conversionName, $force) {
                return $media->dispatchConversion($conversionName, $force);
            })
            ->filter();

    }

    /**
     * @return Collection<int, TMedia>
     */
    public function deleteMediaConversion(
        string $conversionName,
        ?string $collectionName = null,
        ?string $collectionGroup = null,
    ): Collection {
        return $this
            ->getMedia($collectionName, $collectionGroup)
            ->each(function ($media) use ($conversionName) {
                return $media->deleteConversion($conversionName);
            });
    }
}
