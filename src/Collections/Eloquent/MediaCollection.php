<?php

declare(strict_types=1);

namespace Elegantly\Media\Collections\Eloquent;

use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Models\Media;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends Collection<int, Media>
 */
class MediaCollection extends Collection
{
    public function images(): static
    {
        return $this->where('type', MediaType::Image);
    }

    public function videos(): static
    {
        return $this->where('type', MediaType::Video);
    }

    public function audios(): static
    {
        return $this->where('type', MediaType::Audio);
    }

    public function pdfs(): static
    {
        return $this->where('type', MediaType::Pdf);
    }

    public function others(): static
    {
        return $this->where('type', MediaType::Other);
    }
}
