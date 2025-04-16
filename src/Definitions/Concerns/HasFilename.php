<?php

declare(strict_types=1);

namespace Elegantly\Media\Definitions\Concerns;

use Closure;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;

/**
 * @property null|string|(Closure(Media $media, ?MediaConversion $parent):string) $fileName
 */
trait HasFilename
{
    public function getFilename(Media $media, ?MediaConversion $parent): string
    {
        if ($fileName = $this->fileName) {
            return is_string($fileName) ? $fileName : $fileName($media, $parent);
        }

        return $this->getDefaultFilename($media, $parent);
    }

    public function getDefaultFilename(Media $media, ?MediaConversion $parent): string
    {
        $source = $parent ?? $media;

        return "{$source->name}.{$source->extension}";
    }
}
