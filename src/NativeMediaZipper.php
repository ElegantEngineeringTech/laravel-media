<?php

declare(strict_types=1);

namespace Elegantly\Media;

use Elegantly\Media\Exceptions\MediaStreamNotReadableException;
use Elegantly\Media\Models\Media;
use Illuminate\Support\Collection;

/**
 * Zip media using the native `zip` binary instead of PHP.
 * Files are named `{index}_{file_name}` inside the archive folder.
 *
 * @template TMedia of Media
 *
 * @extends AbstractNativeZipper<TMedia>
 */
class NativeMediaZipper extends AbstractNativeZipper
{
    /**
     * Media without a file are skipped.
     *
     * @return Collection<array-key, TMedia>
     */
    protected function getItems(): Collection
    {
        return $this->items->reject(fn ($media) => ! $media->path || ! $media->disk);
    }

    protected function getItemName(mixed $item, int $index): string
    {
        return "{$index}_".($item->file_name ?? $item->id);
    }

    protected function placeItem(mixed $item, string $target): void
    {
        $disk = $item->getDisk();

        if (! $item->path || ! $disk || ! $this->placeFromDisk($disk, $item->path, $target)) {
            throw MediaStreamNotReadableException::forMedia($item);
        }
    }
}
