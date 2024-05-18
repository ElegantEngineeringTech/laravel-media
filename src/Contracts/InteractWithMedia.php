<?php

namespace Finller\Media\Contracts;

use Finller\Media\MediaCollection;
use Finller\Media\MediaConversion;
use Finller\Media\Models\Media;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * @template TMedia of Media
 */
interface InteractWithMedia
{
    /**
     * @return EloquentCollection<int, TMedia>
     */
    public function getMedia(?string $collection_name = null, ?string $collection_group = null): EloquentCollection;

    public function hasMedia(?string $collection_name = null, ?string $collection_group = null): bool;

    /**c
     * @return TMedia
     */
    public function getFirstMedia(?string $collection_name = null, ?string $collection_group = null): ?Media;

    public function getFirstMediaUrl(
        ?string $collection_name = null,
        ?string $collection_group = null,
        ?string $conversion = null,
    ): ?string;

    /**
     * @return Arrayable<int,MediaCollection>|iterable<MediaCollection>|null
     */
    public function registerMediaCollections(): Arrayable|iterable|null;

    /**
     * @param  TMedia  $media
     * @return Arrayable<int,MediaConversion>|iterable<MediaConversion>|null
     */
    public function registerMediaConversions(Media $media): Arrayable|iterable|null;

    /**
     * @return Collection<string, MediaCollection>
     */
    public function getMediaCollections(): Collection;

    public function hasMediaCollection(string $collection_name): bool;

    public function getMediaCollection(string $collection_name): ?MediaCollection;

    /**
     * @param  TMedia  $media
     * @return Collection<string, MediaConversion>
     */
    public function getMediaConversions(Media $media): Collection;

    public function getMediaConversionKey(string $conversion): string;

    /**
     * @param  TMedia  $media
     */
    public function getMediaConversion(Media $media, string $conversion): ?MediaConversion;

    /**
     * @param  int[]  $except  Array of Media Ids
     * @return Collection<int, TMedia> The deleted media list
     */
    public function clearMediaCollection(
        string $collection_name,
        ?string $collection_group = null,
        array $except = []
    ): Collection;

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
    ): Media;

    /**
     * @param  TMedia  $media
     */
    public function dispatchConversion(Media $media, string $conversionName): static;

    /**
     * @param  TMedia  $media
     */
    public function dispatchConversions(
        Media $media,
        ?bool $force = false,
        ?array $only = null,
        ?array $except = null,
    ): static;
}
