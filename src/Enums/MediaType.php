<?php

declare(strict_types=1);

namespace Elegantly\Media\Enums;

use Elegantly\Media\FFMpeg\FFMpeg;
use Elegantly\Media\Helpers\File;

enum MediaType: string
{
    case Video = 'video';
    case Image = 'image';
    case Audio = 'audio';
    case Pdf = 'pdf';
    case Other = 'other';

    public static function tryFromMimeType(string $mimeType): self
    {
        if (str_starts_with($mimeType, 'image/')) {
            return self::Image;
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return self::Audio;
        }

        if (str_starts_with($mimeType, 'video/')) {
            return self::Video;
        }

        if (in_array($mimeType, ['application/pdf', 'application/acrobat', 'application/nappdf', 'application/x-pdf', 'image/pdf'])) {
            return self::Pdf;
        }

        return self::Other;
    }

    /**
     * Some codec like 3GPP or MOV files can contain either audios or videos
     * To determine the true type, we need to check which stream is defined
     */
    public static function tryFromStreams(string $path): self
    {
        $type = self::tryFromMimeType(File::mimeType($path) ?? '');

        if ($type === self::Video || $type === self::Audio) {

            $ffmpeg = new FFMpeg;

            if ($ffmpeg->video()->hasVideo($path)) {
                return self::Video;
            }

            if ($ffmpeg->video()->hasAudio($path)) {
                return self::Audio;
            }

            return self::Other;
        }

        return $type;
    }
}
