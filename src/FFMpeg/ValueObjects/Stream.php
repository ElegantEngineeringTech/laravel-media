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

    /**
     * The codec in the RFC 6381 format, as in the CODECS attribute of HLS playlists or the type of HTML video sources
     * (e.g. "avc1.64001f" for H.264 High profile level 3.1, "mp4a.40.2" for AAC-LC).
     *
     * Notes:
     * - Supports H.264 and AAC, null for other codecs.
     * - Read from the decoder configuration of the stream, only probed with FFMpeg::metadata(extradata: true).
     *   H.264 needs its avcC configuration, stored in MP4, MOV or MKV files but not in MPEG-TS.
     * - Encoders mostly signal HE-AAC as AAC-LC extended with SBR, which only shows in the profile
     *   once ffprobe has decoded some audio.
     */
    public function codecString(): ?string
    {
        if ($this->codecName() === 'h264') {
            $configuration = $this->extradata();

            // The avcC configuration starts with its version, then the profile, constraints and level of the stream
            return $configuration !== null && $configuration[0] === "\x01"
                ? 'avc1.'.bin2hex(mb_substr($configuration, 1, 3))
                : null;
        }

        if ($this->codecName() === 'aac') {
            $configuration = $this->extradata();

            $type = match ($this->getString('profile')) {
                'LC' => 2,
                'HE-AAC' => 5,
                'HE-AACv2' => 29,
                // The AudioSpecificConfig starts with the 5 bits of the audio object type, 31 announcing an extended one
                default => $configuration === null ? null : ord($configuration[0]) >> 3,
            };

            return $type !== null && $type < 31 ? "mp4a.40.{$type}" : null;
        }

        return null;
    }

    /**
     * The codec private data, such as the decoder configuration of H.264 and AAC streams.
     * ffprobe prints it as a hex dump with -show_data: "00000000: 0164 001f ffe1 0018 6764 001f acd9 4050  .d......gd....@P".
     */
    protected function extradata(): ?string
    {
        preg_match_all('/^[0-9a-f]{8}: (?<bytes>[0-9a-f ]+?)  /m', $this->getString('extradata') ?? '', $lines);

        $hex = str_replace(' ', '', implode('', $lines['bytes']));

        return $hex === '' ? null : (string) hex2bin($hex);
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

    /**
     * The average frame rate, from ffprobe "numerator/denominator" format (e.g. "30000/1001").
     * null when unknown (e.g. "0/0").
     */
    public function frameRate(): ?float
    {
        $value = $this->getString('avg_frame_rate') ?? '';

        if (! preg_match('/^(?<numerator>\d+)\/(?<denominator>\d+)$/', $value, $matches)) {
            return null;
        }

        $numerator = (int) $matches['numerator'];
        $denominator = (int) $matches['denominator'];

        if ($numerator === 0 || $denominator === 0) {
            return null;
        }

        return $numerator / $denominator;
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

    /**
     * The YCbCr matrix coefficients (e.g. "bt709", "bt2020nc"), null when unspecified.
     */
    public function colorSpace(): ?string
    {
        return $this->getString('color_space');
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
