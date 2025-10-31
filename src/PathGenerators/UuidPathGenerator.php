<?php

declare(strict_types=1);

namespace Elegantly\Media\PathGenerators;

use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Illuminate\Support\Stringable;

class UuidPathGenerator extends AbstractPathGenerator
{
    /**
     * Ex: {prefix}/{uuid}/
     */
    public function media(Media $media): Stringable
    {
        if ($this->prefix) {
            return str($this->prefix)
                ->finish('/')
                ->append($media->uuid)
                ->finish('/');
        }

        return str($media->uuid)->finish('/');
    }

    /**
     * Adding the mediaConversion uuid ensure the cache is not used
     *
     * @example {prefix}/{uuid}/conversions/poster/{uuid}/
     * @example {prefix}/{uuid}/conversions/poster/conversions/360/{uuid}
     */
    public function conversion(
        Media $media,
        MediaConversion $mediaConversion,
    ): Stringable {

        return $this->media($media)
            ->append('conversions/')
            ->append(str_replace('.', '/conversions/', $mediaConversion->conversion_name))
            ->finish('/')
            ->append($mediaConversion->uuid)
            ->finish('/');
    }
}
