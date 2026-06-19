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

    abstract public function conversion(MediaConversion $mediaConversion): Stringable;

    public function source(Media|MediaConversion $source): Stringable
    {
        if ($source instanceof Media) {
            return $this->media($source);
        }

        return $this->conversion($source);
    }

    public function generate(
        Media|MediaConversion $source,
        string $fileName,
    ): string {
        return $this->source($source)->append($fileName)->value();
    }
}
