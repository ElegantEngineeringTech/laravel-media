<?php

declare(strict_types=1);

namespace Elegantly\Media\Converters\Image;

use Elegantly\Media\Converters\MediaConverter;
use Elegantly\Media\Enums\MediaConversionState;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Spatie\TemporaryDirectory\TemporaryDirectory as SpatieTemporaryDirectory;

class MediaSvgPlaceholderConverter extends MediaConverter
{
    public function __construct(
        public readonly Media $media,
        public int $blur = 50,
        public int $width = 20,
        public ?int $height = 20,
    ) {}

    public function shouldExecute(Media $media, ?MediaConversion $parent): bool
    {
        $source = $parent ?? $media;

        return $source->type === MediaType::Image;
    }

    public function convert(
        Media $media,
        ?MediaConversion $parent,
        ?string $file,
        Filesystem $filesystem,
        SpatieTemporaryDirectory $temporaryDirectory
    ): ?MediaConversion {

        if (! $file) {
            return null;
        }

        $input = $filesystem->path($file);
        $output = $filesystem->path('tiny.jpg');

        Image::load($input)
            ->fit(Fit::Max, $this->width, $this->height)
            ->blur($this->blur)
            ->optimize()
            ->save($output);

        $content = file_get_contents($output);

        if ($content === false) {
            throw new Exception("Can't get file content at {$output}");
        }

        return $media->replaceConversion(new MediaConversion([
            'state' => MediaConversionState::Succeeded,
            'conversion_name' => $this->conversion,
            'content' => base64_encode($content),
            'size' => filesize($output),
        ]));

    }
}
