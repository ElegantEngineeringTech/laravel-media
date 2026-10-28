<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg\ValueObjects;

use Elegantly\Media\FFMpeg\ValueObjects\Concerns\InteractsWithData;
use Elegantly\Media\Helpers\AspectRatio;
use Elegantly\Media\Helpers\Bitrate;
use Elegantly\Media\Helpers\Dimension;

/**
 * A stream entry of ffprobe `-show_streams` output.
 */
class Stream
{
    use InteractsWithData;

    /**
     * @param  array<array-key, mixed>  $data
     */
    final public function __construct(
        public readonly array $data,
    ) {
        //
    }

    public function index(): int
    {
        return $this->getInt('index') ?? 0;
    }

    public function codecName(): ?string
    {
        return $this->getString('codec_name');
    }

    public function codecType(): ?string
    {
        return $this->getString('codec_type');
    }

    public function isAudio(): bool
    {
        return $this->codecType() === 'audio';
    }

    /**
     * A real video stream.
     *
     * Notes:
     * - Filters out MP3/M4A embedded cover art, which ffmpeg exposes as a video stream.
     * - Attempts to avoid "empty" or metadata-only video streams.
     * - Does not rely on `nb_frames` because it is inconsistently present across formats,
     *   especially in WebM/Matroska containers.
     */
    public function isVideo(): bool
    {
        return
            $this->codecType() === 'video' &&
            ! $this->isArtwork() &&
            $this->width() &&
            $this->height();
    }

    /**
     * Embedded artwork (cover image), common in MP3, M4A and FLAC containers.
     */
    public function isArtwork(): bool
    {
        return $this->codecType() === 'video' && $this->getInt('disposition.attached_pic');
    }

    /**
     * Whether the stream is flagged as the default one of its type (e.g. the main audio track).
     */
    public function isDefault(): bool
    {
        return (bool) $this->getInt('disposition.default');
    }

    public function bitrate(): ?Bitrate
    {
        $value = $this->getInt('bit_rate');

        return $value === null ? null : Bitrate::parse($value);
    }

    /**
     * @return float|null The duration in milliseconds
     */
    public function duration(): ?float
    {
        if ($duration = $this->getFloat('duration')) {
            return $duration * 1_000;
        }

        return null;
    }

    public function width(): int
    {
        return $this->getInt('width') ?? 0;
    }

    public function height(): int
    {
        return $this->getInt('height') ?? 0;
    }

    /**
     * The rotation in degrees from the Display Matrix side data
     */
    public function rotation(): int
    {
        $sideDataList = $this->get('side_data_list', []);

        if (! is_array($sideDataList)) {
            return 0;
        }

        foreach ($sideDataList as $sideData) {

            $type = data_get($sideData, 'side_data_type');

            if ($type !== 'Display Matrix') {
                continue;
            }

            $rotation = data_get($sideData, 'rotation');

            return is_numeric($rotation) ? (int) $rotation : 0;
        }

        return 0;
    }

    /**
     * Whether the rotation swaps the width and height (±90° or ±270°).
     */
    public function isFlipped(): bool
    {
        $rotation = $this->rotation();

        return $rotation % 90 === 0 && $rotation % 180 !== 0;
    }

    /**
     * The width to height ratio of a pixel, from ffprobe "width:height" format (e.g. "32:27").
     * 1 when the pixels are square or the ratio is unknown (e.g. "0:1" or "N/A").
     */
    public function sampleAspectRatio(): AspectRatio
    {
        $value = $this->getString('sample_aspect_ratio') ?? '';

        if (! preg_match('/^(?<width>\d+):(?<height>\d+)$/', $value, $matches)) {
            return new AspectRatio(1);
        }

        $width = (int) $matches['width'];
        $height = (int) $matches['height'];

        if ($width === 0 || $height === 0) {
            return new AspectRatio(1);
        }

        return new AspectRatio($width / $height);
    }

    /**
     * The display dimension, with square pixels.
     *
     * Anamorphic videos (non square pixels) are widened rather than shrunk to keep their resolution,
     * e.g. a 720x480 video with a 32:27 sample aspect ratio is displayed at 853x480.
     */
    public function dimension(bool $rotate = true): Dimension
    {
        $sar = $this->sampleAspectRatio();

        // A pixel is $sar times wider than tall
        $dimension = new Dimension(
            $sar->value > 1 ? $sar->getWidth($this->width()) : $this->width(),
            $sar->value < 1 ? $sar->getHeight($this->height()) : $this->height(),
        );

        if ($rotate && $this->isFlipped()) {
            return $dimension->flip();
        }

        return $dimension;
    }

    public function colorTransfer(): ?string
    {
        return $this->getString('color_transfer');
    }

    public function colorPrimaries(): ?string
    {
        return $this->getString('color_primaries');
    }

    /**
     * HDR is defined by its transfer function: PQ (HDR10, HDR10+, Dolby Vision) or HLG.
     *
     * A 10-bit or BT.2020 video alone can still be SDR.
     */
    public function isHdr(): bool
    {
        return in_array($this->colorTransfer(), ['smpte2084', 'arib-std-b67'], true);
    }

    /**
     * Wide color gamut (BT.2020 primaries), used by every HDR video but also by some SDR ones.
     */
    public function isWideGamut(): bool
    {
        return $this->colorPrimaries() === 'bt2020';
    }
}
