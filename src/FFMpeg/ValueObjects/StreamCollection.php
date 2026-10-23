<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg\ValueObjects;

use Illuminate\Support\Collection;

/**
 * @extends Collection<array-key, Stream>
 */
class StreamCollection extends Collection
{
    public function videos(): static
    {
        return $this->filter(fn ($stream) => $stream->isVideo());
    }

    public function audios(): static
    {
        return $this->filter(fn ($stream) => $stream->isAudio());
    }

    public function artworks(): static
    {
        return $this->filter(fn ($stream) => $stream->isArtwork());
    }

    public function defaultVideo(): ?Stream
    {
        $videos = $this->videos();

        return $videos->first(fn ($stream) => $stream->isDefault()) ?? $videos->first();
    }

    public function defaultAudio(): ?Stream
    {
        $audios = $this->audios();

        return $audios->first(fn ($stream) => $stream->isDefault()) ?? $audios->first();
    }

    public function hasAudio(): bool
    {
        return $this->contains(fn ($stream) => $stream->isAudio());
    }

    public function hasVideo(): bool
    {
        return $this->contains(fn ($stream) => $stream->isVideo());
    }

    public function hasArtwork(): bool
    {
        return $this->contains(fn ($stream) => $stream->isArtwork());
    }
}
