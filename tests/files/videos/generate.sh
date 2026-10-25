#!/usr/bin/env bash
#
# Generates lightweight video fixtures covering the formats and edge cases handled by the package.
# Every file is short (1s unless stated otherwise) with a moving test pattern and a sine tone.
#
# Usage: ./tests/files/videos/generate.sh

set -euo pipefail

cd "$(dirname "$0")"

FFMPEG=(ffmpeg -hide_banner -loglevel error -y)
CLEAN=(-map_metadata -1 -fflags +bitexact -flags:v +bitexact -flags:a +bitexact)
H264=(-c:v libx264 -preset veryfast -crf 35 -pix_fmt yuv420p)
AAC=(-c:a aac -b:a 64k -ac 2 -ar 48000)

pattern() { # <width>x<height> [duration] [fps]
    echo "testsrc2=size=$1:duration=${2:-1}:rate=${3:-30}"
}

tone() { # [duration] [frequency]
    echo "sine=duration=${1:-1}:frequency=${2:-440}:sample_rate=48000"
}

# Dimensions and orientations

# 16:9 landscape
"${FFMPEG[@]}" -f lavfi -i "$(pattern 1920x1080)" -f lavfi -i "$(tone)" "${H264[@]}" "${AAC[@]}" "${CLEAN[@]}" landscape-1080p.mp4

# 9:16 portrait stored as portrait
"${FFMPEG[@]}" -f lavfi -i "$(pattern 1080x1920)" -f lavfi -i "$(tone)" "${H264[@]}" "${AAC[@]}" "${CLEAN[@]}" portrait-1080p.mp4

# 9:16 portrait stored as 1920x1080 with a -90° display rotation, like phone recordings
"${FFMPEG[@]}" -f lavfi -i "$(pattern 1920x1080)" -f lavfi -i "$(tone)" "${H264[@]}" "${AAC[@]}" "${CLEAN[@]}" rotated.tmp.mp4
"${FFMPEG[@]}" -display_rotation -90 -i rotated.tmp.mp4 -c copy "${CLEAN[@]}" portrait-rotated.mp4
rm rotated.tmp.mp4

# 1:1 square
"${FFMPEG[@]}" -f lavfi -i "$(pattern 1080x1080)" -f lavfi -i "$(tone)" "${H264[@]}" "${AAC[@]}" "${CLEAN[@]}" square-1080.mp4

# 2.4:1 widescreen, wider than 16:9
"${FFMPEG[@]}" -f lavfi -i "$(pattern 1920x800)" -f lavfi -i "$(tone)" "${H264[@]}" "${AAC[@]}" "${CLEAN[@]}" widescreen-1920x800.mp4

# 4:3, narrower than 16:9
"${FFMPEG[@]}" -f lavfi -i "$(pattern 1440x1080)" -f lavfi -i "$(tone)" "${H264[@]}" "${AAC[@]}" "${CLEAN[@]}" 4-3-1440x1080.mp4

# 4K UHD
"${FFMPEG[@]}" -f lavfi -i "$(pattern 3840x2160 1 10)" -f lavfi -i "$(tone)" "${H264[@]}" "${AAC[@]}" "${CLEAN[@]}" landscape-2160p.mp4

# Smaller than the smallest 240p rendition
"${FFMPEG[@]}" -f lavfi -i "$(pattern 320x180)" -f lavfi -i "$(tone)" "${H264[@]}" "${AAC[@]}" "${CLEAN[@]}" tiny-320x180.mp4

# Non-square pixels: 720x480 stored, displayed as 16:9
"${FFMPEG[@]}" -f lavfi -i "$(pattern 720x480)" -f lavfi -i "$(tone)" -vf setsar=32/27 "${H264[@]}" "${AAC[@]}" "${CLEAN[@]}" anamorphic-720x480.mp4

# Odd dimensions, which libx264 can't encode in 4:2:0
"${FFMPEG[@]}" -f lavfi -i "$(pattern 322x242 1 5)" -vf scale=321:241,setsar=1 "${CLEAN[@]}" odd-321x241.gif
"${FFMPEG[@]}" -f lavfi -i "$(pattern 322x242)" -f lavfi -i "$(tone)" -vf scale=321:241,setsar=1 \
    -c:v libvpx-vp9 -deadline realtime -cpu-used 8 -crf 50 -b:v 0 -c:a libopus -b:a 48k "${CLEAN[@]}" odd-321x241.webm

# Durations and frame rates

# Too short to be segmented in HLS
"${FFMPEG[@]}" -f lavfi -i "$(pattern 1280x720 0.3)" -f lavfi -i "$(tone 0.3)" "${H264[@]}" "${AAC[@]}" "${CLEAN[@]}" short-0.3s.mp4

# 60 fps
"${FFMPEG[@]}" -f lavfi -i "$(pattern 1280x720 1 60)" -f lavfi -i "$(tone)" "${H264[@]}" "${AAC[@]}" "${CLEAN[@]}" 60fps.mp4

# Streams

# Video only
"${FFMPEG[@]}" -f lavfi -i "$(pattern 1280x720)" "${H264[@]}" -an "${CLEAN[@]}" no-audio.mp4

# 5.1 surround audio
"${FFMPEG[@]}" -f lavfi -i "$(pattern 1280x720)" -f lavfi -i "$(tone)" -filter_complex "[1]aformat=channel_layouts=5.1[a]" \
    -map 0 -map "[a]" "${H264[@]}" -c:a aac -b:a 128k "${CLEAN[@]}" surround-5.1.mp4

# A mono commentary track listed before the default stereo track
"${FFMPEG[@]}" -f lavfi -i "$(pattern 1280x720)" -f lavfi -i "$(tone 1 220)" -f lavfi -i "$(tone 1 880)" \
    -map 0 -map 1 -map 2 "${H264[@]}" -c:a aac -b:a:0 32k -ac:a:0 1 -b:a:1 96k -ac:a:1 2 \
    -disposition:a:0 0 -disposition:a:1 default -metadata:s:a:0 title=Commentary -metadata:s:a:1 title=Main \
    -map_metadata -1 -fflags +bitexact multiple-audio.mp4

# Embedded cover art, exposed by ffmpeg as an attached picture video stream
"${FFMPEG[@]}" -f lavfi -i "color=c=red:size=300x300:duration=1" -frames:v 1 cover.tmp.png
"${FFMPEG[@]}" -f lavfi -i "$(pattern 1280x720)" -f lavfi -i "$(tone)" -i cover.tmp.png -map 0 -map 1 -map 2 \
    "${H264[@]}" "${AAC[@]}" -c:v:1 png -disposition:v:1 attached_pic "${CLEAN[@]}" cover-art.mp4
rm cover.tmp.png

# Audio listed before video
"${FFMPEG[@]}" -f lavfi -i "$(tone)" -f lavfi -i "$(pattern 1280x720)" -map 0 -map 1 "${AAC[@]}" "${H264[@]}" "${CLEAN[@]}" audio-first.mkv

# Text subtitles
printf "1\n00:00:00,000 --> 00:00:00,900\nHello\n" > subtitles.tmp.srt
"${FFMPEG[@]}" -f lavfi -i "$(pattern 1280x720)" -f lavfi -i "$(tone)" -i subtitles.tmp.srt -map 0 -map 1 -map 2 \
    "${H264[@]}" "${AAC[@]}" -c:s srt "${CLEAN[@]}" subtitles.mkv
rm subtitles.tmp.srt

# Colors

HEVC=(-c:v libx265 -preset veryfast -crf 35 -tag:v hvc1)

# 10-bit SDR BT.709
"${FFMPEG[@]}" -f lavfi -i "$(pattern 1280x720)" -f lavfi -i "$(tone)" -pix_fmt yuv420p10le "${HEVC[@]}" \
    -x265-params "log-level=error:colorprim=bt709:transfer=bt709:colormatrix=bt709" \
    -color_primaries bt709 -color_trc bt709 -colorspace bt709 "${AAC[@]}" "${CLEAN[@]}" sdr-10bit.mp4

# Wide gamut SDR: BT.2020 primaries without an HDR transfer
"${FFMPEG[@]}" -f lavfi -i "$(pattern 1280x720)" -f lavfi -i "$(tone)" \
    -vf "zscale=tin=bt709:min=bt709:pin=bt709:t=bt2020-10:m=bt2020nc:p=bt2020,format=yuv420p10le" "${HEVC[@]}" \
    -x265-params "log-level=error:colorprim=bt2020:transfer=bt2020-10:colormatrix=bt2020nc" \
    -color_primaries bt2020 -color_trc bt2020-10 -colorspace bt2020nc "${AAC[@]}" "${CLEAN[@]}" wide-gamut-sdr.mp4

# HDR10: PQ with mastering display and content light level metadata, SDR white at 203 nits (BT.2408)
"${FFMPEG[@]}" -f lavfi -i "$(pattern 1280x720)" -f lavfi -i "$(tone)" \
    -vf "zscale=tin=bt709:min=bt709:pin=bt709:t=smpte2084:m=bt2020nc:p=bt2020:npl=203,format=yuv420p10le" "${HEVC[@]}" \
    -x265-params "log-level=error:colorprim=bt2020:transfer=smpte2084:colormatrix=bt2020nc:hdr10=1:master-display=G(13250,34500)B(7500,3000)R(34000,16000)WP(15635,16450)L(10000000,1):max-cll=1000,400" \
    -color_primaries bt2020 -color_trc smpte2084 -colorspace bt2020nc "${AAC[@]}" "${CLEAN[@]}" hdr10.mp4

# HLG, like iPhone HDR recordings, SDR white at 203 nits (BT.2408)
"${FFMPEG[@]}" -f lavfi -i "$(pattern 1280x720)" -f lavfi -i "$(tone)" \
    -vf "zscale=tin=bt709:min=bt709:pin=bt709:t=arib-std-b67:m=bt2020nc:p=bt2020:npl=203,format=yuv420p10le" "${HEVC[@]}" \
    -x265-params "log-level=error:colorprim=bt2020:transfer=arib-std-b67:colormatrix=bt2020nc" \
    -color_primaries bt2020 -color_trc arib-std-b67 -colorspace bt2020nc "${AAC[@]}" "${CLEAN[@]}" hlg.mov

# Metadata

# Location and device tags, like phone recordings
"${FFMPEG[@]}" -f lavfi -i "$(pattern 1280x720)" -f lavfi -i "$(tone)" "${H264[@]}" "${AAC[@]}" -fflags +bitexact \
    -metadata location="+48.8584+002.2945/" \
    -metadata "com.apple.quicktime.location.ISO6709=+48.8584+002.2945+035.000/" \
    -metadata "com.apple.quicktime.make=Apple" \
    -metadata "com.apple.quicktime.model=iPhone" \
    -metadata creation_time="2026-01-01T00:00:00Z" \
    -movflags use_metadata_tags location-metadata.mov
