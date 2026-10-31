<?php

declare(strict_types=1);

namespace Elegantly\Media\Enums;

/**
 * A problem with a video file that the uploader can fix, e.g. by exporting it again.
 */
enum VideoIssue: string
{
    /**
     * ffprobe can't parse the file: corrupted, truncated or not a media file.
     */
    case Unreadable = 'unreadable';

    /**
     * The file has no real video stream, such as an audio file or a 0x0 stream.
     */
    case NoVideoStream = 'no_video_stream';
}
