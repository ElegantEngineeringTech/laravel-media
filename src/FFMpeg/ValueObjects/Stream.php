<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg\ValueObjects;

use Elegantly\Media\FFMpeg\ValueObjects\Concerns\InteractsWithData;
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
     * @return float|null The duration in seconds
     */
    public function duration(): ?float
    {
        return $this->getFloat('duration');
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

    public function dimension(bool $rotate = true): Dimension
    {
        $dimension = new Dimension($this->width(), $this->height());

        if ($rotate && $this->isFlipped()) {
            return $dimension->flip();
        }

        return $dimension;
    }

    public function isHdr(): bool
    {
        if (in_array($this->getString('color_transfer'), ['smpte2084', 'arib-std-b67'], true)) {
            return true;
        }

        if (in_array($this->getString('color_primaries'), ['bt2020'], true)) {
            return true;
        }

        if (in_array($this->getString('color_space'), ['bt2020nc', 'bt2020c'], true)) {
            return true;
        }

        return str_contains($this->getString('pix_fmt') ?? '', '10le');
    }
}
