<?php

declare(strict_types=1);

namespace Elegantly\Media\PathGenerators;

use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Illuminate\Support\Stringable;

abstract class AbstractPathGenerator
{
    public readonly ?string $prefix;

    public function __construct(?string $prefix = null)
    {
        /** @var ?string */
        $config = config('media.generated_path_prefix');

        $this->prefix = $prefix ?? $config;
    }

    abstract public function media(Media $media): Stringable;

    abstract public function conversion(Media $media, MediaConversion $mediaConversion): Stringable;

    public function generate(
        Media $media,
        ?MediaConversion $mediaConversion,
        string $fileName,
    ): string {
        if ($mediaConversion) {
            return $this
                ->conversion($media, $mediaConversion)
                ->append($fileName)
                ->value();
        }

        return $this
            ->media($media)
            ->append($fileName)
            ->value();
    }
}
