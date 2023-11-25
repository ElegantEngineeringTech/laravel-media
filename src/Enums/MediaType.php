<?php

namespace Finller\LaravelMedia\Enums;

enum MediaType: string
{
    case Video = 'video';
    case Image = 'image';
    case Audio = 'audio';
    case Pdf = 'pdf';
    case Other = 'other';


    static function tryFromMimeType(string $mimeType)
    {
        if (str_starts_with($mimeType, 'video/')) {
            return self::Video;
        }

        if (str_starts_with($mimeType, 'image/')) {
            return self::Image;
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return self::Audio;
        }

        if (in_array($mimeType, ['application/pdf', 'application/acrobat', 'application/nappdf', 'application/x-pdf', 'image/pdf'])) {
            return self::Pdf;
        }

        return self::Other;
    }
}
