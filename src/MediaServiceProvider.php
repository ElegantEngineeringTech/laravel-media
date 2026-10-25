<?php

declare(strict_types=1);

namespace Elegantly\Media;

use Elegantly\Media\Commands\GenerateMediaConversionsCommand;
use Elegantly\Media\Commands\RetryMediaConversionsCommand;
use Elegantly\Media\FFMpeg\FFMpeg;
use Elegantly\Media\Helpers\Bitrate;
use Elegantly\Media\Helpers\VideoRendition;
use Elegantly\Media\Helpers\VideoRenditionCollection;
use Elegantly\Media\Jobs\DeleteMediaFileJob;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MediaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-media')
            ->hasConfigFile()
            ->hasMigration('create_media_table')
            ->hasMigration('create_media_conversions_table')
            ->hasMigration('migrate_generated_conversions_to_media_conversions_table')
            ->hasMigration('migrate_state_in_media_conversions_table')
            ->hasMigration('add_additional_files_to_media_conversions_table')
            ->hasMigration('add_additional_files_to_media_table')
            ->hasCommand(GenerateMediaConversionsCommand::class)
            ->hasCommand(RetryMediaConversionsCommand::class)
            ->hasViews();
    }

    public function registeringPackage(): void
    {
        $this->app->scoped(FFMpeg::class, function () {

            return new FFMpeg(
                // @phpstan-ignore-next-line
                ffmpeg: config('media.ffmpeg.ffmpeg_binaries'),
                // @phpstan-ignore-next-line
                ffprobe: config('media.ffmpeg.ffprobe_binaries'),
                // @phpstan-ignore-next-line
                logChannel: config('media.ffmpeg.log_channel'),
            );

        });
    }

    /**
     * @return class-string<DeleteMediaFileJob>
     */
    public static function getDeleteMediaFileJob(): string
    {
        // @phpstan-ignore-next-line
        return config('media.delete_media_file_job');
    }

    public static function getVideoRenditions(): VideoRenditionCollection
    {
        return new VideoRenditionCollection(array_map(
            fn ($value) => new VideoRendition($value),// @phpstan-ignore-line
            config('media.videos.renditions')// @phpstan-ignore-line
        ));
    }

    public static function getVideoHlsRenditions(): VideoRenditionCollection
    {
        $renditions = static::getVideoRenditions();

        /**
         * @var array<int, int|array{size: int, maxrate: int|float|string|Bitrate, bufsize: int|float|string|Bitrate, audioBitrate: int|float|string|Bitrate}>
         */
        $values = config('media.videos.hls_renditions');

        /**
         * @var array<int, array{size: int, maxrate: int|float|string|Bitrate, bufsize: int|float|string|Bitrate, audioBitrate: int|float|string|Bitrate}>
         */
        $items = [];

        foreach ($values as $value) {

            if (is_array($value)) {
                $items[] = $value;
            } elseif ($rendition = $renditions->firstWhere('size', $value)) {
                $items[] = $rendition->toArray();
            }

        }

        return new VideoRenditionCollection(array_map(
            fn ($value) => new VideoRendition($value),
            $items
        ));
    }
}
