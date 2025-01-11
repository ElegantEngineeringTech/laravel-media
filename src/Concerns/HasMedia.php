<?php

declare(strict_types=1);

namespace Elegantly\Media\Concerns;

use Elegantly\Media\Events\MediaAddedEvent;
use Elegantly\Media\Helpers\File as HelpersFile;
use Elegantly\Media\Jobs\DeleteModelMediaJob;
use Elegantly\Media\MediaCollection;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Bus\PendingDispatch;
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
     * @return TMedia
     */
    public function addMedia(
        mixed $file,
        ?string $collectionName = null,
        ?string $collectionGroup = null,
        ?string $name = null,
        ?string $disk = null,
    ): Media {
        $collectionName ??= config('media.default_collection_name');

        /** @var class-string<TMedia> */
        $model = config('media.model');

        $media = new $model;
        $media->model()->associate($this);
        $media->collection_name = $collectionName;
        $media->collection_group = $collectionGroup;

        $collection = $collectionName ? $this->getMediaCollection($collectionName) : null;

        $media->storeFile(
            file: $file,
            name: $name,
            disk: $disk ?? $collection?->disk,
            before: function ($file) use ($collection) {
                if ($acceptedMimeTypes = $collection?->acceptedMimeTypes) {
                    $mime = HelpersFile::mimeType($file);

                    if (! in_array($mime, $acceptedMimeTypes)) {
                        throw new Exception(
                            "Media file can't be stored: Invalid MIME type: {$mime}. Accepted MIME types are: ".implode(', ', $acceptedMimeTypes),
                            415
                        );
                    }
                }

                if ($transform = $collection?->transform) {
                    return $transform($file);
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
     * @param  int|TMedia  $media
     */
    public function dispatchMediaConversion(
        int|Media $media,
        string $conversion
    ): ?PendingDispatch {

        $media = $media instanceof Media ? $media : $this->media->find($media);

        $media->model()->associate($this);

        return $media->dispatchConversion($conversion);
    }

    /**
     * @param  int|TMedia  $media
     */
    public function executeMediaConversion(
        int|Media $media,
        string $conversion
    ): ?MediaConversion {

        $media = $media instanceof Media ? $media : $this->media->find($media);

        $media->model()->associate($this);

        return $media->executeConversion($conversion);
    }
}
