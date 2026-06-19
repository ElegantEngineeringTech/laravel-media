<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg;

use Elegantly\Media\FFMpeg\Exceptions\VideoStreamNotFoundException;
use Elegantly\Media\Helpers\Bitrate;
use Elegantly\Media\Helpers\HlsVariant;
use Elegantly\Media\Helpers\Video as HelpersVideo;
use Illuminate\Support\Facades\File;

class Video extends FFMpeg
{
    public function metadata(string $input): array
    {
        $output = $this->ffprobe([
            '-v', 'error',
            '-select_streams', 'v:0',
            '-show_format',
            '-show_streams',
            '-print_format', 'json',
            $input,
        ]);

        $metadata = json_decode($output, true);

        // @phpstan-ignore-next-line
        return is_array($metadata) ? $metadata : [];
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public function dimensions(string $input): array
    {
        $metadata = $this->metadata($input);

        if ($stream = $metadata['streams'][0] ?? null) {

            // @phpstan-ignore-next-line
            $width = (int) data_get($stream, 'width');
            // @phpstan-ignore-next-line
            $height = (int) data_get($stream, 'height');
            // @phpstan-ignore-next-line
            $rotation = (int) data_get($stream, 'side_data_list.0.rotation', 0);

            return [$width, $height, $rotation];
        }

        throw VideoStreamNotFoundException::atPath($input);
    }

    /**
     * @return float The duration in milliseconds
     */
    public function duration(string $input): float
    {
        $metadata = $this->metadata($input);

        /** @var float|int|string */
        $duration = data_get($metadata, 'format.duration');

        $duration = (float) $duration;

        return $duration * 1_000;
    }

    public function isHdr(string $input): bool
    {
        $metadata = $this->metadata($input);

        if (! $stream = $metadata['streams'][0] ?? null) {
            throw VideoStreamNotFoundException::atPath($input);
        }

        $colorTransfer = data_get($stream, 'color_transfer');

        if (in_array($colorTransfer, ['smpte2084', 'arib-std-b67'], true)) {
            return true;
        }

        $colorPrimaries = data_get($stream, 'color_primaries');

        if (in_array($colorPrimaries, ['bt2020'], true)) {
            return true;
        }

        $colorSpace = data_get($stream, 'color_space');

        if (in_array($colorSpace, ['bt2020nc', 'bt2020c'], true)) {
            return true;
        }

        $pixFmt = data_get($stream, 'pix_fmt');

        if (is_string($pixFmt) && str_contains($pixFmt, '10le')) {
            return true;
        }

        return false;
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
     */
    public function thumbnail(
        string $input,
        string $output,
        int $frames = 90,
        int|float|string $timecode = '00:00:00',
        ?int $width = null,
        ?int $height = null,
    ): string {
        return $this->frame(
            input: $input,
            output: $output,
            timecode: $timecode,
            width: $width,
            height: $height,
            filters: ["thumbnail={$frames}"],
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
        string $preset = 'veryslow'
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
     * The output directory will contain a master playlist and one media playlist per generated resolution.
     * Renditions are only generated when the source display height is at least as large as the target height.
     *
     * @param  string  $input  The absolute path to the source video file.
     * @param  string  $output  The directory where HLS playlists and segments should be written.
     * @param  string  $playlist  The master playlist filename.
     * @param  int  $segmentLength  HLS segment length in seconds.
     * @param  string  $preset  The x264 compression speed preset.
     * @param  null|string[]  $variants
     * @param  list<array{name: string, height: int, bitrate: string, maxrate: string, bufsize: string, audioBitrate: string}>  $variantsOptions
     * @return false|string Command output
     */
    public function hls(
        string $input,
        string $output,
        string $playlist = 'master.m3u8',
        int $segmentLength = 6,
        string $preset = 'veryslow',
        ?array $variants = null,
        ?array $variantsOptions = null,
    ): false|string {
        $output = rtrim(rtrim($output), DIRECTORY_SEPARATOR);

        $variantsOptions ??= [
            ['name' => '2160p', 'height' => 2160, 'bitrate' => '25000k', 'maxrate' => '26750k', 'bufsize' => '37500k', 'audioBitrate' => '256k'],
            ['name' => '1440p', 'height' => 1440, 'bitrate' => '8000k', 'maxrate' => '8560k', 'bufsize' => '12000k', 'audioBitrate' => '256k'],
            ['name' => '1080p', 'height' => 1080, 'bitrate' => '5000k', 'maxrate' => '5350k', 'bufsize' => '7500k', 'audioBitrate' => '256k'],
            ['name' => '720p', 'height' => 720, 'bitrate' => '2800k', 'maxrate' => '2996k', 'bufsize' => '4200k', 'audioBitrate' => '256k'],
            ['name' => '480p', 'height' => 480, 'bitrate' => '1400k', 'maxrate' => '1498k', 'bufsize' => '2100k', 'audioBitrate' => '192k'],
            ['name' => '360p', 'height' => 360, 'bitrate' => '800k', 'maxrate' => '856k', 'bufsize' => '1200k', 'audioBitrate' => '128k'],
            ['name' => '240p', 'height' => 240, 'bitrate' => '600k', 'maxrate' => '642k', 'bufsize' => '900k', 'audioBitrate' => '96k'],
        ];

        $dimension = HelpersVideo::dimension($input);

        $selectedVariants = collect($variantsOptions)
            ->mapInto(HlsVariant::class)
            ->when(
                $variants,
                fn ($items) => $items->whereIn('name', $variants ?? [])
            )
            ->where('height', '<=', $dimension->height)
            ->values();

        if ($selectedVariants->isEmpty()) {
            return false;
        }

        $metadata = parent::metadata($input);
        $hasAudio = false;
        $sourceVideoBitrate = null;
        $sourceAudioBitrate = null;

        foreach ($metadata['streams'] ?? [] as $stream) {
            $codecType = $stream['codec_type'] ?? null;

            if ($codecType === 'video') {
                $sourceVideoBitrate ??= Bitrate::parse($stream['bit_rate'] ?? null);
            }

            if ($codecType === 'audio') {
                $sourceAudioBitrate ??= Bitrate::parse($stream['bit_rate'] ?? null);
                $hasAudio = true;
            }
        }

        if (! File::isDirectory($output)) {
            File::makeDirectory($output, recursive: true);
        }

        $splitLabels = [];
        $filters = [];

        foreach ($selectedVariants as $index => $variant) {
            $splitLabels[] = "[v{$index}]";
            $filters[] = implode(':', ["[v{$index}]scale=w=-2", "h={$variant->height}", "flags=lanczos,format=yuv420p[v{$index}out]"]);
        }

        $filterComplex = '[0:v:0]split='.count($selectedVariants).implode('', $splitLabels).';'.implode(';', $filters);

        $arguments = [
            '-i', $input,
            '-filter_complex', $filterComplex,
        ];

        foreach ($selectedVariants as $index => $variant) {
            $arguments[] = '-map';
            $arguments[] = "[v{$index}out]";

            if ($hasAudio) {
                $arguments[] = '-map';
                $arguments[] = '0:a:0';
            }
        }

        $arguments = [
            ...$arguments,
            '-c:v', 'libx264',
            '-preset', $preset,
            '-profile:v', 'main',
            '-pix_fmt', 'yuv420p',
            '-sc_threshold', '0',
            '-force_key_frames', "expr:gte(t,n_forced*{$segmentLength})",
        ];

        foreach ($selectedVariants as $index => $variant) {
            $bitrate = $variant->bitrate->max($sourceVideoBitrate);
            $maxrate = $variant->maxrate->max($sourceVideoBitrate);
            $bufsize = $variant->bufsize->max($bitrate->value * 1.5);

            $arguments = [
                ...$arguments,
                "-b:v:{$index}", $bitrate->format(),
                "-maxrate:v:{$index}", $maxrate->format(),
                "-bufsize:v:{$index}", $bufsize->format(),
            ];
        }

        if ($hasAudio) {
            $arguments = [
                ...$arguments,
                '-c:a', 'aac',
                '-ac', '2',
                '-ar', '48000',
            ];

            foreach ($selectedVariants as $index => $variant) {
                $arguments = [
                    ...$arguments,
                    "-b:a:{$index}", $variant->audioBitrate->max($sourceAudioBitrate)->format(),
                ];
            }
        } else {
            $arguments[] = '-an';
        }

        $variantStreamMap = [];

        foreach ($selectedVariants as $index => $variant) {
            if ($hasAudio) {
                $variantStreamMap[] = "v:{$index},a:{$index},name:{$variant->name}";
            } else {
                $variantStreamMap[] = "v:{$index},name:{$variant->name}";
            }
        }

        return $this->ffmpeg([
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
    }
}
