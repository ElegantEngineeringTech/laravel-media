<?php

namespace Elegantly\Media\Contracts;

use Elegantly\Media\MediaCollection;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection;
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
interface InteractWithMedia
{
    /**
     * @return MorphMany<TMedia>
     */
    public function media(): MorphMany;

    /**
     * @return Arrayable<array-key, MediaCollection>|iterable<MediaCollection>|null
     */
    public function registerMediaCollections(): Arrayable|iterable|null;

    public function getMediaCollection(string $collectionName): ?MediaCollection;

    /**
     * @return Collection<int, TMedia>
     */
    public function getMedia(
        ?string $collectionName = null,
        ?string $collectionGroup = null
    ): Collection;

    public function hasMedia(
        ?string $collectionName = null,
        ?string $collectionGroup = null
    ): bool;

    /**
     * @return ?TMedia
     */
    public function getFirstMedia(
        ?string $collectionName = null,
        ?string $collectionGroup = null
    ): ?Media;

    /**
     * @param  array<array-key, mixed>  $parameters
     */
    public function getFirstMediaUrl(
        ?string $collectionName = null,
        ?string $collectionGroup = null,
        ?string $conversion = null,
        ?array $parameters = null,
    ): ?string;

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
    ): Media;

    /**
     * @return $this
     */
    public function deleteMedia(int $mediaId): static;

    /**
     * @param  array<array-key, string|int>  $except
     * @return $this
     */
    public function clearMediaCollection(
        string $collectionName,
        ?string $collectionGroup = null,
        array $except = [],
    ): static;

    /**
     * @param  int|TMedia  $media
     */
    public function dispatchMediaConversion(
        int|Media $media,
        string $conversion
    ): ?PendingDispatch;

    /**
     * @param  int|TMedia  $media
     */
    public function executeMediaConversion(
        int|Media $media,
        string $conversion
    ): ?MediaConversion;
}
