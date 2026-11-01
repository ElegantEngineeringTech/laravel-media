<?php

declare(strict_types=1);

namespace Elegantly\Media\Converters\Video;

use Elegantly\Media\Converters\MediaConverter;
use Elegantly\Media\Enums\MediaType;
use Elegantly\Media\Facades\FFMpeg;
use Elegantly\Media\Helpers\File;
use Elegantly\Media\Helpers\Spritesheet;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\TemporaryDirectory;
use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * Tiles frames of a video into sprite sheets, for timeline previews in video players or previews scrubbed on hover.
 *
 * Like an HLS playlist, the WebVTT file mapping each time range to its frame is the conversion file,
 * and the sheets it references are additional files stored next to it.
 * The layout is stored in the conversion metadata, see Spritesheet::toArray().
 *
 * @see Spritesheet
 */
class MediaSpritesheetConverter extends MediaConverter
{
    /**
     * @param  string  $filename  The WebVTT file, also naming the sheets (e.g. sprites_0.jpg, sprites_1.jpg for sprites.vtt).
     *                            Use a .vtt extension.
     * @param  string  $format  The image format of the sheets: jpg, webp or png.
     * @param  int|float|null  $interval  The time between two frames in seconds, e.g. 2 for a frame every 2 seconds.
     *                                    null spreads the frames evenly over the whole video.
     * @param  int|null  $frames  The maximum number of frames, the interval being widened on long videos to stay under it.
     *                            Defaults to a single sheet (columns × rows) without interval, and to no limit otherwise.
     * @param  int|null  $width  The maximum width of a frame in pixels, keeping the aspect ratio. Frames are never upscaled.
     * @param  int|null  $height  The maximum height of a frame in pixels, keeping the aspect ratio.
     * @param  int  $columns  The number of frames per row.
     * @param  int  $rows  The maximum number of rows of a sheet, the following frames going to a new sheet.
     */
    public function __construct(
        Media $media,
        public string $filename = 'spritesheet.vtt',
        public string $format = 'jpg',
        public int|float|null $interval = null,
        public ?int $frames = null,
        public ?int $width = 160,
        public ?int $height = null,
        public int $columns = 10,
        public int $rows = 10,
    ) {
        parent::__construct($media);

        $this->filename = File::sanitizeBasename($filename);
    }

    public function canExecute(Media $media, ?MediaConversion $parent): bool
    {
        $source = $parent ?? $media;

        return $source->type === MediaType::Video || in_array($source->mime_type, ['image/gif']);
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

        $name = pathinfo($this->filename, PATHINFO_FILENAME);
        $extension = pathinfo($this->filename, PATHINFO_EXTENSION);

        $input = $filesystem->path($file);
        $output = $filesystem->path("{$name}.{$extension}");

        $ffmpeg = FFMpeg::video();

        if (! $ffmpeg->hasVideo($input)) {
            return $this->unsupportedConversion();
        }

        $spritesheet = $ffmpeg->spritesheet(
            input: $input,
            output: $output,
            format: $this->format,
            interval: $this->interval,
            frames: $this->frames,
            width: $this->width,
            height: $this->height,
            upscale: false,
            columns: $this->columns,
            rows: $this->rows,
        );

        if ($spritesheet === false) {
            return $this->unsupportedConversion();
        }

        return $media->addConversion(
            file: $output,
            conversionName: $this->conversion,
            parent: $parent,
            metadata: $spritesheet->toArray(),
            additionalFiles: array_map(
                fn ($sheet) => $filesystem->path($sheet),
                $spritesheet->sheets,
            ),
        );

    }
}
