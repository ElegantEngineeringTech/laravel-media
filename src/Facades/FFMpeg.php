<?php

declare(strict_types=1);

namespace Elegantly\Media\Facades;

use Elegantly\Media\FFMpeg\Audio;
use Elegantly\Media\FFMpeg\ValueObjects\Metadata;
use Elegantly\Media\FFMpeg\ValueObjects\StreamCollection;
use Elegantly\Media\FFMpeg\Video;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Video video()
 * @method static Audio audio()
 * @method static Metadata metadata(string $input)
 * @method static StreamCollection decodableStreams(string $input)
 * @method static string[] decodableCodecs()
 * @method static \Elegantly\Media\FFMpeg\FFMpeg setDecodableCodecs(string[]|null $codecs)
 * @method static string stripMetadata(string $input, string $output)
 * @method static string ffmpeg(string[] $arguments)
 * @method static string ffprobe(string[] $arguments)
 * @method static bool hasAudio(string $input)
 * @method static bool hasVideo(string $input)
 * @method static bool hasArtwork(string $input)
 * @method static float duration(string $input) The duration in milliseconds
 *
 * @see \Elegantly\Media\FFMpeg\FFMpeg
 */
class FFMpeg extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Elegantly\Media\FFMpeg\FFMpeg::class;
    }
}
