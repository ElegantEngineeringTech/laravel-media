<?php

declare(strict_types=1);

namespace Elegantly\Media\Enums;

use Elegantly\Media\FFMpeg\FFMpeg;
use Elegantly\Media\Helpers\Audio;
use Elegantly\Media\Helpers\Dimension;
use Elegantly\Media\Helpers\File;
use Elegantly\Media\Helpers\Image;
use Elegantly\Media\Helpers\Video;

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

        if (
            in_array($mimeType, ['application/vnd.apple.mpegurl']) ||
            str_starts_with($mimeType, 'video/')
        ) {
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
    public static function guess(string $path): self
    {
        $mimeType = File::mimeType($path);

        if ($mimeType === null) {
            return self::Other;
        }

        if (in_array($mimeType, ['application/vnd.apple.mpegurl'])) {
            return self::Video;
        }

        $guessed = self::tryFromMimeType($mimeType);

        if (in_array($guessed, [self::Video, self::Audio])) {

            $ffmpeg = new FFMpeg;

            if ($ffmpeg->hasVideo($path)) {
                return self::Video;
            }

            if ($ffmpeg->hasAudio($path)) {
                return self::Audio;
            }

            return self::Other;
        }

        return $guessed;
    }

    public function duration(string $path): ?float
    {
        return match ($this) {
            MediaType::Video => Video::duration($path),
            MediaType::Audio => Audio::duration($path),
            default => null,
        };
    }

    public function dimension(string $path): ?Dimension
    {
        return match ($this) {
            MediaType::Video => Video::dimension($path),
            MediaType::Image => Image::dimension($path),
            default => null
        };
    }
}
