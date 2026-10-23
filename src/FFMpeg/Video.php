<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg;

use Elegantly\Media\FFMpeg\Exceptions\VideoStreamNotFoundException;
use Elegantly\Media\FFMpeg\ValueObjects\Stream;
use Elegantly\Media\Helpers\Dimension;
use Elegantly\Media\Helpers\HlsVariants;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class Video extends FFMpeg
{
    /**
     * The default video stream, ignoring embedded cover art.
     */
    public function videoStream(string $input): Stream
    {
        $stream = $this->metadata($input)->streams()->defaultVideo();

        if (! $stream) {
            throw VideoStreamNotFoundException::atPath($input);
        }

        return $stream;
    }

    public function dimension(string $input, bool $rotate = true): Dimension
    {
        return $this->videoStream($input)->dimension($rotate);
    }

    public function rotation(string $input): int
    {
        return $this->videoStream($input)->rotation();
    }

    public function isHdr(string $input): bool
    {
        return $this->videoStream($input)->isHdr();
    }

    /**
     * @param  int|float|string  $timecode  in seconds (SS.xxx) or formatted (HH:MM:SS.xx)
     * @param  string[]  $filters  Additional ffmpeg filters
     */
    public function frame(
        string $input,
        string $output,
        int|float|string $timecode = '00:00:00',
        ?int $width = null,
        ?int $height = null,
        array $filters = []
    ): string {

        $filters = implode(',', [
            ...$filters,
            $this->sdrFilter($this->videoStream($input)),
            $this->getScale($width, $height),
        ]);

        return $this->ffmpeg([
            '-ss', (string) $timecode,
            '-i', $input,
            '-frames:v', '1',
            '-vf', $filters,
            $output,
        ]);
    }

    /**
     * Extract a representative thumbnail using FFmpeg's thumbnail filter.
     *
     * @param  int  $frames  Number of frames to analyze per batch
     * @param  string[]  $filters  Additional ffmpeg filters
     */
    public function thumbnail(
        string $input,
        string $output,
        int $frames = 90,
        int|float|string $timecode = '00:00:00',
        ?int $width = null,
        ?int $height = null,
        array $filters = [],
    ): string {
        return $this->frame(
            input: $input,
            output: $output,
            timecode: $timecode,
            width: $width,
            height: $height,
            filters: [
                "thumbnail={$frames}",
                ...$filters,
            ],
        );
    }

    /**
     * Transcodes a video file to MP4 (H.264/AAC) using FFmpeg.
     * This method optimizes the video for web playback by applying a YUV420p pixel format and the +faststart flag.
     *
     * @param  string  $input  The absolute path to the source video file.
     * @param  string  $output  The absolute path where the output MP4 should be saved.
     * @param  int|null  $width  The target width in pixels. If null, original width is kept.
     * @param  int|null  $height  The target height in pixels. If null, original height is kept.
     * @param  int|null  $fps  Target FPS (capped at source rate).
     * @param  int  $crf  Constant Rate Factor (0–51).
     *                    18 is visually lossless;
     *                    23 is default;
     *                    higher is lower quality.
     * @param  string  $preset  The compression speed (e.g., 'ultrafast', 'medium', 'veryslow').
     *                          Slower presets result in better compression/smaller files.
     */
    public function mp4(
        string $input,
        string $output,
        ?int $width = null,
        ?int $height = null,
        ?int $fps = null,
        int $crf = 18,
        string $preset = 'slow'
    ): string {

        $stream = $this->videoStream($input);

        $filters = implode(',', [
            $this->sdrFilter($stream),
            $this->getScale($width, $height),
            $fps ? "fps=fps=min({$fps}\,source_fps)" : 'null',
        ]);

        return $this->ffmpeg([
            '-i', $input,
            '-vf', $filters,
            '-c:v', 'libx264',
            '-crf', (string) $crf,
            '-preset', $preset,
            '-pix_fmt', 'yuv420p',
            '-c:a', 'aac',
            '-b:a', '128k',
            '-movflags', '+faststart',
            $output,
        ]);
    }

    /**
     * Transcodes a video file to WebM (VP9/Opus) using FFmpeg.
     *
     * This method uses the libvpx-vp9 encoder in Constrained Quality (CQ) mode.
     * It enables multi-threading and modern audio compression (Opus) for superior web performance.
     *
     * @param  string  $input  The absolute path to the source video file.
     * @param  string  $output  The absolute path for the output .webm file.
     * @param  int|null  $width  Target width in pixels. Auto-scales if null.
     * @param  int|null  $height  Target height in pixels. Auto-scales if null.
     * @param  int|null  $fps  Target FPS (capped at source rate).
     * @param  int  $crf  Constant Rate Factor (0–63).
     *                    Lower is higher quality.
     *                    31–34 is recommended for 1080p;
     *                    15-18 for high-quality archiving.
     * @param  string  $deadline  The quality/speed tradeoff: 'realtime', 'good', or 'best'.
     *                            'good' is the recommended balance.
     * @param  int  $cpuUsed  Encoding efficiency (0–8). Higher values speed up encoding at a slight cost to quality.
     */
    public function webm(
        string $input,
        string $output,
        ?int $width = null,
        ?int $height = null,
        ?int $fps = null,
        int $crf = 32,
        string $deadline = 'good',
        int $cpuUsed = 3,
    ): string {

        $stream = $this->videoStream($input);

        $filters = implode(',', [
            $this->sdrFilter($stream),
            $this->getScale($width, $height),
            $fps ? "fps=fps=min({$fps}\,source_fps)" : 'null',
        ]);

        return $this->ffmpeg([
            '-i', $input,
            '-vf', $filters,
            '-c:v', 'libvpx-vp9',
            '-crf', (string) $crf,
            '-b:v', '0',
            '-pix_fmt', 'yuv420p',
            '-row-mt', '1',
            '-deadline', $deadline,
            '-cpu-used', (string) $cpuUsed,
            '-c:a', 'libopus',
            '-b:a', '96k',
            '-movflags', '+faststart',
            $output,
        ]);
    }

    /**
     * Generate a production-ready HLS VOD playlist with adaptive m3u8 variants.
     *
     * The output directory will contain a master playlist, one media playlist per generated resolution
     * and, when the source has audio, one audio-only rendition per distinct variant audio bitrate,
     * shared by every video rendition using that bitrate (a single one with the default variants).
     *
     * Each variant describes a 16:9 bounding box (e.g. 1080p is 1920x1080, or 1080x1920 for portrait sources).
     * The video is scaled to fit inside the box while keeping its aspect ratio, and a variant is only generated
     * when the source fills at least one side of the box, so the video is never upscaled.
     *
     * Renditions are encoded in capped CRF: x264 targets a constant quality, spending fewer bits on simple scenes,
     * while the variant maxrate and bufsize cap the bitrate of complex ones so each rendition stays within its bandwidth.
     *
     * @param  string  $input  The absolute path to the source video file.
     * @param  string  $output  The directory where HLS playlists and segments should be written.
     * @param  string  $playlist  The master playlist filename.
     * @param  int|null  $fps  Target FPS (capped at source rate).
     * @param  int  $segmentLength  HLS segment length in seconds.
     * @param  string  $preset  The x264 compression speed preset.
     * @param  int  $crf  The x264 Constant Rate Factor (0–51), the quality targeted by every rendition.
     *                    Lower is higher quality; 23 is the x264 default and a common choice for streaming.
     *                    The variant maxrate still caps the bitrate.
     * @param  HlsVariants|null  $variants  The renditions to generate, among those the source supports.
     *                                      Their maxrate and audio bitrate are capped to the source bitrates.
     * @param  int|float  $startingSize  Size of the variant listed first in the master playlist (e.g. 720 for 720p).
     *                                   Apple players start playback on it, so a mid-quality one gives a fast startup.
     * @param  int|float  $keyframeInterval  Maximum time between keyframes in seconds (Apple recommends 2).
     *                                       Shorter intervals make seeking faster at a small bitrate cost.
     *                                       Keyframes always land on segment boundaries.
     * @return false|string Command output, or false when the source can't produce a playable stream
     *                      (smaller than every variant, or too short to be segmented)
     */
    public function hls(
        string $input,
        string $output,
        string $playlist = 'master.m3u8',
        ?int $fps = null,
        int $segmentLength = 6,
        int $crf = 23,
        string $preset = 'slow',
        ?HlsVariants $variants = null,
        int|float $startingSize = 720,
        int|float $keyframeInterval = 2,
    ): false|string {
        $variants ??= HlsVariants::defaults();

        $output = rtrim(rtrim($output), DIRECTORY_SEPARATOR);

        $streams = $this->metadata($input)->streams();

        $videoStream = $streams->defaultVideo();

        if (! $videoStream) {
            throw VideoStreamNotFoundException::atPath($input);
        }

        $sourceDimension = $videoStream->dimension(rotate: true);

        $variants = $variants->supportedBy($sourceDimension);

        if ($variants->isEmpty()) {
            return false;
        }

        $audioStream = $streams->defaultAudio();

        $variants = $variants
            ->setMaxBitrate($videoStream->bitrate())
            ->setMaxAudioBitrate($audioStream?->bitrate());

        // Variants sharing the same audio bitrate share a single audio rendition.
        // Without audio, the list is empty so no audio rendition is mapped nor listed.
        $audioBitrates = $audioStream ? $variants->audioBitrates()->unique()->values()->all() : [];

        $audioGroup = fn (string $audioBitrate) => "audio_{$audioBitrate}";

        // Apple players start playback on the first variant of the master playlist.
        // Variants can't be empty here, the fallback only satisfies the nullable return type.
        $startingVariantKey = $variants->getKeyClosestTo($startingSize) ?? 0;

        // Keyframes at most every $keyframeInterval seconds for faster seeking,
        // always landing on segment boundaries so renditions stay aligned.
        $keyframesPerSegment = (int) ceil($segmentLength / $keyframeInterval);

        $arguments = [
            '-c:v', 'libx264',
            '-preset', $preset,
            '-crf', (string) $crf,
            '-profile:v', 'high',
            '-pix_fmt', 'yuv420p',
            '-sc_threshold', '0',
            '-force_key_frames', "expr:gte(t,n_forced*{$segmentLength}/{$keyframesPerSegment})",
            '-forced-idr', '1',
            // Prevent x264 from inserting its own keyframes (every 250 frames by default)
            '-x264-params', 'keyint=infinite',
        ];

        if ($audioStream) {
            array_push(
                $arguments,
                '-c:a', 'aac',
                '-ac', '2', // Stereo, supported by every player
                '-ar', '48000', // Standard rate for video, used by most sources
            );
        } else {
            array_push(
                $arguments,
                '-an'
            );
        }

        $fpsFilter = $fps ? "fps=fps=min({$fps}\,source_fps)" : 'null';

        $splitLabels = [];
        $filters = [];
        $variantStreamMap = [];

        // Each rendition's filter, output options and playlist entry are built together.
        // Options apply to the whole output, only the order of the -map options defines the output stream indexes.
        foreach ($variants as $index => $variant) {

            $variantDimension = $variant->dimension(portrait: $sourceDimension->isPortrait());

            $scaleFilter = 'scale='.implode(':', [
                "w={$variantDimension->width}",
                "h={$variantDimension->height}",
                'force_original_aspect_ratio=decrease',
                'force_divisible_by=2',
                'flags=lanczos',
            ]);

            $splitLabels[] = "[v{$index}]";
            $filters[] = "[v{$index}]{$scaleFilter},{$fpsFilter}[v{$index}out]";

            array_push(
                $arguments,
                '-map', "[v{$index}out]",
                "-maxrate:v:{$index}", $variant->maxrate->format(),
                "-bufsize:v:{$index}", $variant->bufsize->format(),
            );

            $variantStreamMap[$index] = implode(',', array_filter([
                "v:{$index}",
                $audioStream ? 'agroup:'.$audioGroup($variant->audioBitrate->format()) : null,
                "name:{$variant->name}",
            ]));

        }

        $audioStreamMap = [];

        foreach ($audioBitrates as $index => $audioBitrate) {
            array_push(
                $arguments,
                '-map', "0:{$audioStream->index()}",
                "-b:a:{$index}", $audioBitrate,
            );

            // default:yes prevents ffmpeg from also listing the audio as a standalone variant
            $audioStreamMap[] = "a:{$index},agroup:{$audioGroup($audioBitrate)},name:{$audioGroup($audioBitrate)},default:yes";
        }

        // The source is converted to SDR once, before being split into renditions
        $filterComplex = "[0:{$videoStream->index()}]".implode(';', [
            implode(',', [
                $this->sdrFilter($videoStream),
                'split='.count($splitLabels).implode('', $splitLabels),
            ]),
            ...$filters,
        ]);

        $variantStreamMap = [
            ...$audioStreamMap,
            $variantStreamMap[$startingVariantKey],
            ...Arr::except($variantStreamMap, $startingVariantKey),
        ];

        File::ensureDirectoryExists($output);

        $result = $this->ffmpeg([
            '-i', $input,
            '-filter_complex', $filterComplex,
            ...$arguments,
            '-f', 'hls',
            '-hls_time', (string) $segmentLength,
            '-hls_playlist_type', 'vod',
            '-hls_flags', 'independent_segments',
            '-start_number', '0',
            '-master_pl_name', $playlist,
            '-var_stream_map', implode(' ', $variantStreamMap),
            '-hls_segment_filename', "{$output}/%v_segment_%05d.ts",
            "{$output}/%v_playlist.m3u8",
        ]);

        // ffmpeg can't measure the bandwidth of sources shorter than ~0.5s
        // and then silently writes a master playlist without any variant
        $master = "{$output}/{$playlist}";

        if (
            ! File::exists($master) ||
            ! str_contains(File::get($master), '#EXT-X-STREAM-INF')
        ) {
            return false;
        }

        return $result;
    }

    /**
     * Filters converting a video stream to 8-bit SDR BT.709 (yuv420p), the format every browser and device can decode.
     *
     * HDR (PQ or HLG) and wide gamut (BT.2020) videos go through zscale, which reads the source transfer,
     * primaries and matrix from the frame metadata: the signal is linearized (1.0 = 100 nits, the SDR reference
     * white expected by tonemap), converted to BT.709 primaries, then encoded back to BT.709.
     * HDR highlights are compressed in between with the Möbius curve, which keeps SDR-range content almost unchanged.
     * Its peak defaults to 1000 nits, the BT.2100 nominal peak of HLG (e.g. iPhone videos) and the usual HDR10 mastering peak.
     *
     * Other SDR videos are only converted to 8-bit 4:2:0, keeping their colors untouched.
     */
    protected function sdrFilter(Stream $stream): string
    {
        if (
            ! $stream->isHdr() &&
            ! $stream->isWideGamut()
        ) {
            return 'format=yuv420p';
        }

        return implode(',', array_filter([
            'zscale=transfer=linear:npl=100',
            'format=gbrpf32le',
            'zscale=primaries=bt709',
            $stream->isHdr() ? 'tonemap=tonemap=mobius:desat=0' : null,
            'zscale=transfer=bt709:matrix=bt709:range=tv',
            'format=yuv420p',
        ]));
    }
}
