<?php

namespace Elegantly\Media\Contracts;

use Elegantly\Media\MediaCollection;
use Elegantly\Media\MediaConversion;
use Elegantly\Media\Models\Media;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\File;
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

    /**
     * @return ?TMedia
     */
    public function getFirstMedia(?string $collection_name = null, ?string $collection_group = null);

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
     * @return Arrayable<int|string,MediaConversion>|iterable<MediaConversion>|null
     */
    public function registerMediaConversions($media): Arrayable|iterable|null;

    /**
     * @param  TMedia  $media
     */
    public function registerMediaTransformations($media, UploadedFile|File $file): UploadedFile|File;

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
    public function getMediaConversions($media): Collection;

    public function getMediaConversionKey(string $conversion): string;

    /**
     * @param  TMedia  $media
     */
    public function getMediaConversion($media, string $conversion): ?MediaConversion;

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
     * @return ?TMedia
     */
    public function deleteMedia(int $mediaId);

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
    );

    /**
     * @param  TMedia  $media
     */
    public function dispatchConversion($media, string $conversionName): static;

    /**
     * @param  TMedia  $media
     */
    public function dispatchConversions(
        $media,
        ?bool $force = false,
        ?array $only = null,
        ?array $except = null,
    ): static;
}
