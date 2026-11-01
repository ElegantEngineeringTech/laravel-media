<?php

declare(strict_types=1);

namespace Elegantly\Media\Converters\Image;

use Elegantly\Media\Converters\MediaConverter;
use Elegantly\Media\Enums\MediaConversionState;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Exceptions\FileContentNotFoundException;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\TemporaryDirectory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

/**
 * Generates a tiny, blurred JPEG placeholder of the image.
 * The result is stored base64-encoded in the conversion's `contents` instead of on a disk.
 */
class MediaImagePlaceholderConverter extends MediaConverter
{
    /**
     * @param  int  $blur  Blur amount, from 0 (none) to 100 (maximum).
     * @param  int  $width  Maximum width in pixels. The aspect ratio is preserved and the image is never upscaled.
     * @param  null|int  $height  Maximum height in pixels.
     *                            - null : Only the width is constrained.
     */
    public function __construct(
        Media $media,
        public int $blur = 20,
        public int $width = 20,
        public ?int $height = 20,
    ) {
        parent::__construct($media);
    }

    public function canExecute(Media $media, ?MediaConversion $parent): bool
    {
        $source = $parent ?? $media;

        return $source->type === MediaType::Image;
    }

    public function convert(
        Media $media,
        ?MediaConversion $parent,
        ?string $file,
        Filesystem $filesystem,
        TemporaryDirectory $temporaryDirectory
    ): ?MediaConversion {

        if (! $file) {
            return $this->unsupportedConversion();
        }

        $input = $filesystem->path($file);
        $output = $filesystem->path('tiny.jpg');

        Image::useImageDriver(config('media.images.driver'))
            ->loadFile($input)
            ->fit(Fit::Max, $this->width, $this->height)
            ->blur($this->blur)
            ->optimize()
            ->save($output);

        $content = file_get_contents($output);

        if ($content === false) {
            throw FileContentNotFoundException::atPath($output);
        }

        return $media->replaceConversion([
            'state' => MediaConversionState::Succeeded,
            'conversion_name' => $this->conversion,
            'contents' => base64_encode($content),
            'size' => filesize($output),
        ]);

    }
}
