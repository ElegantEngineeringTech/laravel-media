<?php

declare(strict_types=1);

namespace Elegantly\Media\FFMpeg;

use Elegantly\Media\FFMpeg\Exceptions\VideoStreamNotFoundException;
use Elegantly\Media\FFMpeg\ValueObjects\Stream;
use Elegantly\Media\Helpers\Dimension;
use Elegantly\Media\Helpers\VideoRenditionCollection;
use Elegantly\Media\MediaServiceProvider;
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
     * @param  bool  $upscale  Whether the frame can be larger than the source.
     * @param  string[]  $filters  Additional ffmpeg filters
     * @param  bool  $stripMetadata  Remove the source metadata (e.g. location, device, dates) from the output.
     */
    public function frame(
        string $input,
        string $output,
        int|float|string $timecode = '00:00:00',
        ?int $width = null,
        ?int $height = null,
        bool $upscale = true,
        array $filters = [],
        bool $stripMetadata = true,
    ): string {

        $filters = implode(',', [
            ...$filters,
            $this->sdrFilter($this->videoStream($input)),
            $this->scaleFilter($width, $height, $upscale),
        ]);

        return $this->ffmpeg([
            '-ss', (string) $timecode,
            '-i', $input,
            '-frames:v', '1',
            '-vf', $filters,
            ...($stripMetadata ? ['-map_metadata', '-1'] : []),
            $output,
        ]);
    }

    /**
     * Extract a representative thumbnail using FFmpeg's thumbnail filter.
     *
     * @param  int  $frames  Number of frames to analyze per batch
     * @param  bool  $upscale  Whether the thumbnail can be larger than the source.
     * @param  string[]  $filters  Additional ffmpeg filters
     * @param  bool  $stripMetadata  Remove the source metadata (e.g. location, device, dates) from the output.
     */
    public function thumbnail(
        string $input,
        string $output,
        int $frames = 90,
        int|float|string $timecode = '00:00:00',
        ?int $width = null,
        ?int $height = null,
        bool $upscale = true,
        array $filters = [],
        bool $stripMetadata = true,
    ): string {
        return $this->frame(
            input: $input,
            output: $output,
            timecode: $timecode,
            width: $width,
            height: $height,
            upscale: $upscale,
            filters: [
                "thumbnail={$frames}",
                ...$filters,
            ],
            stripMetadata: $stripMetadata,
        );
    }

    /**
     * Transcodes a video file to MP4 (H.264/AAC) using FFmpeg.
     * This method optimizes the video for web playback by applying a YUV420p pixel format and the +faststart flag.
     *
     * The video is encoded in capped CRF: x264 targets a constant quality, spending fewer bits on simple scenes,
     * while complex ones are capped to the maxrate and bufsize of the video rendition of the same size (and to the source bitrate),
     * so the file can be played progressively without buffering.
     *
     * @param  string  $input  The absolute path to the source video file.
     * @param  string  $output  The absolute path where the output MP4 should be saved.
     * @param  int|null  $width  The maximum width in pixels, keeping the aspect ratio.
     * @param  int|null  $height  The maximum height in pixels, keeping the aspect ratio.
     * @param  bool  $upscale  Whether the video can be larger than the source.
     * @param  int|null  $fps  Target FPS (capped at the source and rendition frame rates).
     * @param  int|float  $keyframeInterval  Time between keyframes in seconds.
     * @param  int  $crf  The x264 Constant Rate Factor (0–51), the targeted quality.
     *                    Lower is higher quality; 23 is the x264 default and a common choice for the web.
     * @param  string  $preset  The compression speed (e.g., 'ultrafast', 'medium', 'veryslow').
     *                          Slower presets result in better compression/smaller files.
     * @param  bool  $stripMetadata  Remove the source metadata (e.g. location, device, dates) from the output.
     */
    public function mp4(
        string $input,
        string $output,
        ?int $width = null,
        ?int $height = null,
        bool $upscale = true,
        ?int $fps = null,
        int|float $keyframeInterval = 2,
        int $crf = 23,
        string $preset = 'slow',
        bool $stripMetadata = true,
    ): string {

        $streams = $this->metadata($input)->streams();

        $video = $streams->defaultVideo();

        if (! $video) {
            throw VideoStreamNotFoundException::atPath($input);
        }

        $audio = $streams->defaultAudio();

        $dimension = $video->dimension()->scale($width, $height, $upscale);

        $rendition = MediaServiceProvider::getVideoRenditions()
            ->getBestSupportedBy($dimension)
            ->setMaxBitrate($video->bitrate())
            ->setMaxAudioBitrate($audio?->bitrate())
            ->setMaxFps($fps);

        $filters = implode(',', [
            $this->sdrFilter($video),
            $this->fpsFilter($rendition->fps),
            $this->scaleFilter($width, $height, $upscale, even: true),
        ]);

        return $this->ffmpeg([
            '-i', $input,
            '-vf', $filters,
            '-c:v', 'libx264',
            '-crf', (string) $crf,
            '-force_key_frames', "expr:gte(t,n_forced*{$keyframeInterval})",
            '-maxrate', $rendition->maxrate->format(),
            '-bufsize', $rendition->bufsize->format(),
            '-preset', $preset,
            '-pix_fmt', 'yuv420p',
            '-c:a', 'aac',
            '-b:a', $rendition->audioBitrate->format(),
            '-ac', '2', // Stereo
            '-ar', '48000', // Standard rate for video
            '-movflags', '+faststart',
            ...($stripMetadata ? ['-map_metadata', '-1'] : []),
            $output,
        ]);
    }

    /**
     * Transcodes a video file to WebM (VP9/Opus) using FFmpeg.
     *
     * This method uses the libvpx-vp9 encoder in Constrained Quality mode: the crf sets the targeted quality,
     * while the average bitrate is capped to the maxrate of the video rendition of the same size (and to the source bitrate).
     * It enables multi-threading and modern audio compression (Opus) for superior web performance.
     *
     * @param  string  $input  The absolute path to the source video file.
     * @param  string  $output  The absolute path for the output .webm file.
     * @param  int|null  $width  The maximum width in pixels, keeping the aspect ratio.
     * @param  int|null  $height  The maximum height in pixels, keeping the aspect ratio.
     * @param  bool  $upscale  Whether the video can be larger than the source.
     * @param  int|null  $fps  Target FPS (capped at the source and rendition frame rates).
     * @param  int|float  $keyframeInterval  Time between keyframes in seconds.
     * @param  int  $crf  Constant Rate Factor (0–63).
     *                    Lower is higher quality.
     *                    31–34 is recommended for 1080p;
     *                    15-18 for high-quality archiving.
     * @param  string  $deadline  The quality/speed tradeoff: 'realtime', 'good', or 'best'.
     *                            'good' is the recommended balance.
     * @param  int  $cpuUsed  Encoding efficiency (0–8). Higher values speed up encoding at a slight cost to quality.
     * @param  bool  $stripMetadata  Remove the source metadata (e.g. location, device, dates) from the output.
     */
    public function webm(
        string $input,
        string $output,
        ?int $width = null,
        ?int $height = null,
        bool $upscale = true,
        ?int $fps = null,
        int|float $keyframeInterval = 2,
        int $crf = 32,
        string $deadline = 'good',
        int $cpuUsed = 3,
        bool $stripMetadata = true,
    ): string {

        $streams = $this->metadata($input)->streams();

        $video = $streams->defaultVideo();

        if (! $video) {
            throw VideoStreamNotFoundException::atPath($input);
        }

        $audio = $streams->defaultAudio();

        $dimension = $video->dimension()->scale($width, $height, $upscale);

        $rendition = MediaServiceProvider::getVideoRenditions()
            ->getBestSupportedBy($dimension)
            ->setMaxBitrate($video->bitrate())
            ->setMaxAudioBitrate($audio?->bitrate())
            ->setMaxFps($fps);

        $filters = implode(',', [
            $this->sdrFilter($video),
            $this->fpsFilter($rendition->fps),
            $this->scaleFilter($width, $height, $upscale),
        ]);

        return $this->ffmpeg([
            '-i', $input,
            '-vf', $filters,
            '-c:v', 'libvpx-vp9',
            '-crf', (string) $crf,
            '-b:v', $rendition->maxrate->format(),
            '-force_key_frames', "expr:gte(t,n_forced*{$keyframeInterval})",
            '-pix_fmt', 'yuv420p',
            '-row-mt', '1',
            '-deadline', $deadline,
            '-cpu-used', (string) $cpuUsed,
            '-c:a', 'libopus',
            '-b:a', $rendition->audioBitrate->format(),
            '-ac', '2', // Stereo
            '-cues_to_front', '1', // Seek index at the start, for faster playback start and seeking
            ...($stripMetadata ? ['-map_metadata', '-1'] : []),
            $output,
        ]);
    }

    /**
     * Generate a production-ready HLS VOD playlist with adaptive m3u8 variants.
     *
     * The output directory will contain a master playlist and one media playlist per generated resolution.
     * When the source has audio, it is muxed into every rendition at the rendition audio bitrate,
     * so the bandwidth ffmpeg measures for each variant includes it.
     *
     * Each rendition describes a 16:9 bounding box (e.g. 1080p is 1920x1080, or 1080x1920 for portrait sources).
     * The video is scaled to fit inside the box while keeping its aspect ratio, and a rendition is only generated
     * when the source fills at least one side of the box, so the video is never upscaled.
     * Sources smaller than every rendition (e.g. 320x180) get the smallest one, at their own size.
     *
     * Renditions are encoded in capped CRF: x264 targets a constant quality, spending fewer bits on simple scenes,
     * while the rendition maxrate and bufsize cap the bitrate of complex ones so each rendition stays within its bandwidth.
     *
     * @param  string  $input  The absolute path to the source video file.
     * @param  string  $output  The directory where HLS playlists and segments should be written.
     * @param  string  $playlist  The master playlist filename.
     * @param  int|null  $fps  Target FPS (capped at the source and rendition frame rates).
     * @param  int  $segmentLength  HLS segment length in seconds.
     * @param  int  $crf  The x264 Constant Rate Factor (0–51), the quality targeted by every rendition.
     *                    Lower is higher quality; 23 is the x264 default and a common choice for streaming.
     *                    The rendition maxrate still caps the bitrate.
     * @param  string  $preset  The x264 compression speed preset.
     * @param  VideoRenditionCollection|null  $renditions  The renditions to generate, among those the source supports.
     *                                                     Their maxrate and audio bitrate are capped to the source bitrates.
     * @param  int  $startingRendition  Size of the rendition listed first in the master playlist (e.g. 720 for 720p).
     *                                  Apple players start playback on it, so a mid-quality one gives a fast startup.
     * @param  int|float  $keyframeInterval  Maximum time between keyframes in seconds (Apple recommends 2).
     *                                       Shorter intervals make seeking faster at a small bitrate cost.
     *                                       Keyframes always land on segment boundaries.
     * @param  bool  $stripMetadata  Remove the source metadata (e.g. location, device, dates) from the output.
     * @return false|string Command output, or false when the source can't produce a playable stream
     *                      (no renditions given, or too short to be segmented)
     */
    public function hls(
        string $input,
        string $output,
        string $playlist = 'master.m3u8',
        ?int $fps = null,
        int $segmentLength = 6,
        int $crf = 23,
        string $preset = 'slow',
        ?VideoRenditionCollection $renditions = null,
        int $startingRendition = 720,
        int|float $keyframeInterval = 2,
        bool $stripMetadata = true,
    ): false|string {
        $output = rtrim(rtrim($output), DIRECTORY_SEPARATOR);
        $renditions ??= MediaServiceProvider::getVideoHlsRenditions();

        $streams = $this->metadata($input)->streams();

        $videoStream = $streams->defaultVideo();

        if (! $videoStream) {
            throw VideoStreamNotFoundException::atPath($input);
        }

        $sourceDimension = $videoStream->dimension(rotate: true);

        if ($renditions->isEmpty()) {
            return false;
        }

        // Sources smaller than every rendition get the smallest one, scaled down to their own size
        $renditions = $renditions->supportedBy($sourceDimension)->whenEmpty(
            fn () => $renditions->sortBy('size')->take(1)->values()
        );

        $audioStream = $streams->defaultAudio();

        $renditions = $renditions
            ->setMaxBitrate($videoStream->bitrate())
            ->setMaxAudioBitrate($audioStream?->bitrate())
            ->setMaxFps($fps);

        // Apple players start playback on the first variant of the master playlist.
        $startingRenditionKey = $renditions->getClosestToKey($startingRendition) ?? 0;

        // Keyframes at most every $keyframeInterval seconds for faster seeking,
        // always landing on segment boundaries so renditions stay aligned.
        $keyframesPerSegment = (int) ceil($segmentLength / $keyframeInterval);

        $arguments = [
            '-c:v', 'libx264',
            '-preset', $preset,
            '-crf', (string) $crf,
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
                '-ac', '2', // Stereo
                '-ar', '48000', // Standard rate for video
            );
        } else {
            array_push(
                $arguments,
                '-an'
            );
        }

        $splitLabels = [];
        $filters = [];
        $variantStreamMap = [];

        // Options apply to the whole output, only the order of the -map options defines the output stream indexes.
        foreach ($renditions as $index => $rendition) {

            $renditionDimension = $rendition->dimension(
                portrait: $sourceDimension->isPortrait()
            );

            // Supported renditions never upscale, this only clamps the fallback rendition to the source size
            $scaleFilter = $this->scaleFilter(
                width: $renditionDimension->width,
                height: $renditionDimension->height,
                upscale: false,
                even: true
            );

            $fpsFilter = $this->fpsFilter($rendition->fps);

            $splitLabels[] = "[v{$index}]";
            $filters[] = "[v{$index}]{$fpsFilter},{$scaleFilter}[v{$index}out]";

            array_push(
                $arguments,
                '-map', "[v{$index}out]",
                "-maxrate:v:{$index}", $rendition->maxrate->format(),
                "-bufsize:v:{$index}", $rendition->bufsize->format(),
            );

            // Each rendition gets its own copy of the audio, as ffmpeg leaves the audio out of
            // the BANDWIDTH of variants referencing a separate audio rendition.
            if ($audioStream) {
                array_push(
                    $arguments,
                    '-map', "0:{$audioStream->index()}",
                    "-b:a:{$index}", $rendition->audioBitrate->format(),
                );
            }

            $variantStreamMap[$index] = implode(',', array_filter([
                "v:{$index}",
                $audioStream ? "a:{$index}" : null,
                "name:{$rendition->size}p",
            ]));

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
            $variantStreamMap[$startingRenditionKey],
            ...Arr::except($variantStreamMap, $startingRenditionKey),
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
            '-hls_segment_type', 'fmp4',
            // Written next to the media playlists
            '-hls_fmp4_init_filename', '%v_init.mp4',
            '-start_number', '0',
            '-master_pl_name', $playlist,
            '-var_stream_map', implode(' ', $variantStreamMap),
            ...($stripMetadata ? ['-map_metadata', '-1'] : []),
            '-hls_segment_filename', "{$output}/%v_segment_%05d.m4s",
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

    /**
     * Fit the video inside the given width and/or height, keeping its aspect ratio.
     *
     * The output always has square pixels: anamorphic videos (e.g. 720x480 DVDs displayed at 16:9) are scaled
     * to their display size, widening them rather than losing resolution (720x480 becomes 853x480),
     * so players and HLS RESOLUTION attributes see the real size.
     * Without a width nor a height, the video is only scaled when its pixels are not square.
     *
     * @param  bool  $upscale  Whether the video can be larger than the source display size.
     * @param  bool  $even  Round the dimensions to even numbers, as required by libx264 for 4:2:0 video (e.g. for odd sized GIFs).
     */
    protected function scaleFilter(
        ?int $width = null,
        ?int $height = null,
        bool $upscale = true,
        bool $even = false,
    ): string {
        // The display size, see Stream::dimension()
        $displayWidth = 'max(iw*sar\,iw)';
        $displayHeight = 'max(ih/sar\,ih)';

        // Without upscaling, the box is clamped to the display size
        $boxWidth = $width && ! $upscale ? "min({$width}\\,{$displayWidth})" : $width;
        $boxHeight = $height && ! $upscale ? "min({$height}\\,{$displayHeight})" : $height;

        return 'scale='.implode(':', [
            'w='.($boxWidth ?? ($height ? -1 : $displayWidth)),
            'h='.($boxHeight ?? ($width ? -1 : $displayHeight)),
            'force_original_aspect_ratio=decrease',
            'force_divisible_by='.($even ? 2 : 1),
            'reset_sar=1',
            'flags=lanczos',
        ]);
    }

    /**
     * Cap the frame rate without judder, never raising the source frame rate.
     *
     * Dropping frames evenly requires an integer fraction of the source rate (e.g. 50 → 25, 59.94 → 29.97, 120 → 30),
     * while capping 50fps to exactly 30fps keeps 3 frames out of 5 and makes motion stutter.
     * The largest fraction under the cap is used, unless it falls below 75% of the cap (e.g. 30 → 15 for a 24fps cap),
     * in which case the cap itself is used.
     */
    protected function fpsFilter(?int $fps): string
    {
        if (! $fps) {
            return 'null';
        }

        $fraction = "source_fps/ceil(source_fps/{$fps})";

        return "fps=fps=min(source_fps\,if(gte({$fraction}\,{$fps}*0.75)\,{$fraction}\,{$fps}))";
    }
}
