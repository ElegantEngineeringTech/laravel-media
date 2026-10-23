<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg;

use Elegantly\Media\FFMpeg\Exceptions\VideoStreamNotFoundException;
use Elegantly\Media\Helpers\Dimension;
use Elegantly\Media\Helpers\HlsVariants;
use Illuminate\Support\Facades\File;

class Video extends FFMpeg
{
    public function dimension(string $input, bool $rotate = true): Dimension
    {
        $stream = $this->metadata($input)->streams()->videos()->defaultVideo();

        if (! $stream) {
            throw VideoStreamNotFoundException::atPath($input);
        }

        return $stream->dimension($rotate);
    }

    public function rotation(string $input): int
    {
        $stream = $this->metadata($input)->streams()->videos()->defaultVideo();

        if (! $stream) {
            throw VideoStreamNotFoundException::atPath($input);
        }

        return $stream->rotation();
    }

    public function isHdr(string $input): bool
    {
        return $this
            ->metadata($input)
            ->streams()
            ->videos()
            ->contains(fn ($stream) => $stream->isHdr());
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
            'format=yuv420p', // Fix Invalid color space caused by HDR
            $this->getScale($width, $height),
        ]);

        return $this->ffmpeg([
            '-ss', (string) $timecode,
            '-i', $input,
            '-vframes', '1',
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

        $filters = implode(',', [
            'format=yuv420p', // Fix Invalid color space caused by HDR
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

        $filters = implode(',', [
            $this->getScale($width, $height),
            $fps ? "fps=fps=min({$fps}\,source_fps)" : 'null',
        ]);

        return $this->ffmpeg([
            '-i', $input,
            '-vf', $filters,
            '-c:v', 'libvpx-vp9',
            '-crf', (string) $crf,
            '-b:v', '0',
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
     * @param  string  $input  The absolute path to the source video file.
     * @param  string  $output  The directory where HLS playlists and segments should be written.
     * @param  string  $playlist  The master playlist filename.
     * @param  int|null  $fps  Target FPS (capped at source rate).
     * @param  int  $segmentLength  HLS segment length in seconds.
     * @param  string  $preset  The x264 compression speed preset.
     * @return false|string Command output, or false when the source can't produce a playable stream
     *                      (smaller than every variant, or too short to be segmented)
     */
    public function hls(
        string $input,
        string $output,
        string $playlist = 'master.m3u8',
        ?int $fps = null,
        int $segmentLength = 6,
        string $preset = 'slow',
        ?HlsVariants $variants = null,
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

        $sourceVideoBitrate = $videoStream->bitrate();
        $sourceAudioBitrate = $audioStream?->bitrate();

        if ($sourceVideoBitrate?->isNotZero()) {
            $variants = $variants->setMaxBitrate($sourceVideoBitrate);
        }

        if ($sourceAudioBitrate?->isNotZero()) {
            $variants = $variants->setMaxAudioBitrate($sourceAudioBitrate);
        }

        $splitLabels = [];
        $filters = [];

        $fpsFilter = $fps ? "fps=fps=min({$fps}\,source_fps)" : 'null';

        foreach ($variants as $index => $variant) {
            $splitLabels[] = "[v{$index}]";

            $variantDimension = $variant->dimension(portrait: $sourceDimension->isPortrait());

            $scaleFilter = 'scale='.implode(':', [
                "w={$variantDimension->width}",
                "h={$variantDimension->height}",
                'force_original_aspect_ratio=decrease',
                'force_divisible_by=2',
                'flags=lanczos',
            ]);

            $filters[] = "[v{$index}]".implode(',', [
                $scaleFilter,
                $fpsFilter,
            ])."[v{$index}out]";

        }

        $splitFilter = 'split='.count($splitLabels).implode('', $splitLabels);

        $filterComplex = "[0:{$videoStream->index()}]".implode(';', [
            $splitFilter,
            ...$filters,
        ]);

        $arguments = [
            '-i', $input,
            '-filter_complex', $filterComplex,
        ];

        // Variants sharing the same audio bitrate share a single audio rendition.
        // Without audio, the list is empty so no audio rendition is mapped nor listed.
        $audioBitrates = $audioStream
            ? $variants->audioBitrates()->unique()->values()->all()
            : [];

        $audioGroup = fn (string $audioBitrate) => "audio_{$audioBitrate}";

        // Each rendition's settings are grouped together; options apply to the whole output,
        // only the order of the -map options defines the output stream indexes
        foreach ($variants as $index => $variant) {
            array_push(
                $arguments,
                '-map', "[v{$index}out]",
                "-b:v:{$index}", $variant->bitrate->format(),
                "-maxrate:v:{$index}", $variant->maxrate->format(),
                "-bufsize:v:{$index}", $variant->bufsize->format(),
            );
        }

        foreach ($audioBitrates as $index => $audioBitrate) {
            array_push(
                $arguments,
                '-map', "0:{$audioStream->index()}",
                "-b:a:{$index}", $audioBitrate,
            );
        }

        // Keyframes every 2s at most (Apple recommendation) for faster seeking,
        // always landing on segment boundaries so renditions stay aligned.
        $keyframesPerSegment = (int) ceil($segmentLength / 2);

        array_push(
            $arguments,
            '-c:v', 'libx264',
            '-preset', $preset,
            '-profile:v', 'high',
            '-pix_fmt', 'yuv420p',
            '-sc_threshold', '0',
            '-force_key_frames', "expr:gte(t,n_forced*{$segmentLength}/{$keyframesPerSegment})",
            '-forced-idr', '1',
            // Prevent x264 from inserting its own keyframes (every 250 frames by default)
            '-x264-params', 'keyint=infinite',
        );

        if ($audioStream) {
            array_push(
                $arguments,
                '-c:a', 'aac',
                '-ac', '2',
                '-ar', '48000',
            );
        } else {
            $arguments[] = '-an';
        }

        // Apple players start playback on the first variant of the master playlist,
        // so the one closest to 720p is listed first for a fast startup.
        $startingVariantKey = $variants->getKeyClosestTo(720);

        $variantStreamMap = [];

        foreach ($variants as $index => $variant) {
            $entry = $audioStream
                ? "v:{$index},agroup:{$audioGroup($variant->audioBitrate->format())},name:{$variant->name}"
                : "v:{$index},name:{$variant->name}";

            if ($index === $startingVariantKey) {
                array_unshift($variantStreamMap, $entry);
            } else {
                $variantStreamMap[] = $entry;
            }
        }

        $audioStreamMap = [];

        foreach ($audioBitrates as $index => $audioBitrate) {
            // default:yes prevents ffmpeg from also listing the audio as a standalone variant
            $audioStreamMap[] = "a:{$index},agroup:{$audioGroup($audioBitrate)},name:{$audioGroup($audioBitrate)},default:yes";
        }

        $variantStreamMap = [...$audioStreamMap, ...$variantStreamMap];

        File::ensureDirectoryExists($output);

        $result = $this->ffmpeg([
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
}
