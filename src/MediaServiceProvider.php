<?php

declare(strict_types=1);

namespace Elegantly\Media;

use Elegantly\Media\Commands\GenerateMediaConversionsCommand;
use Elegantly\Media\Commands\RegenerateMediaConversionsCommand;
use Elegantly\Media\Commands\RetryMediaConversionsCommand;
use Elegantly\Media\FFMpeg\FFMpeg;
use Elegantly\Media\Helpers\Bitrate;
use Elegantly\Media\Helpers\VideoRendition;
use Elegantly\Media\Helpers\VideoRenditionCollection;
use Elegantly\Media\Jobs\DeleteMediaFileJob;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\PathGenerators\AbstractPathGenerator;
use Elegantly\Media\UrlFormatters\AbstractUrlFormatter;
use LogicException;
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
            ->hasCommand(RegenerateMediaConversionsCommand::class)
            ->hasViews()
            ->hasTranslations();
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
                // @phpstan-ignore-next-line
                timeout: config('media.ffmpeg.timeout'),
            );

        });
    }

    /**
     * @return class-string<DeleteMediaFileJob>
     */
    public static function getDeleteMediaFileJob(): string
    {
        /** @var class-string<DeleteMediaFileJob> */
        $value = config()->string('media.delete_media_file_job');

        return $value;
    }

    /**
     * @param  string  $codec  The key of the renditions in media.videos.renditions (e.g. 'mp4' or 'webm').
     * @param  null|int[]  $only
     */
    public static function getVideoRenditions(string $codec = 'mp4', ?array $only = null): VideoRenditionCollection
    {
        /**
         * @var null|array{size: int, maxrate: int|float|string|Bitrate, bufsize: int|float|string|Bitrate, audioBitrate: int|float|string|Bitrate}[]
         */
        $values = config("media.videos.renditions.{$codec}");

        if ($values === null) {
            throw new LogicException("The video renditions media.videos.renditions.{$codec} are not defined.");
        }

        $renditions = new VideoRenditionCollection(array_map(
            fn ($value) => new VideoRendition($value),
            $values
        ));

        if ($only !== null) {
            $renditions = $renditions->onlySizes(
                values: $only,
                strict: true
            );
        }

        return $renditions;
    }

    /**
     * @param  null|int[]  $only
     */
    public static function getVideoHlsRenditions(?array $only = null): VideoRenditionCollection
    {
        /**
         * @var array<int, int|array{size: int, maxrate: int|float|string|Bitrate, bufsize: int|float|string|Bitrate, audioBitrate: int|float|string|Bitrate}>
         */
        $values = config('media.videos.hls.renditions');

        $renditions = static::getVideoRenditions('mp4');

        $renditions = new VideoRenditionCollection(array_map(
            function ($value) use ($renditions) {

                if (is_array($value)) {
                    return new VideoRendition($value);
                }

                if ($rendition = $renditions->firstWhere('size', $value)) {
                    return $rendition;
                }

                throw new LogicException("The HLS rendition size {$value} in media.videos.hls.renditions is not defined in media.videos.renditions.mp4.");
            },
            $values
        ));

        if ($only !== null) {
            $renditions = $renditions->onlySizes(
                values: $only,
                strict: true
            );
        }

        return $renditions;
    }

    /**
     * @return class-string<AbstractUrlFormatter>
     */
    public static function getDefaultUrlFormatter(): string
    {
        /** @var class-string<AbstractUrlFormatter> */
        $value = config()->string('media.default_url_formatter');

        return $value;
    }

    /**
     * @return class-string<MediaConversion>
     */
    public static function getMediaConversionModel(): string
    {
        /** @var class-string<MediaConversion> */
        $value = config()->string('media.media_conversion_model');

        return $value;
    }

    /**
     * @return class-string<Media>
     */
    public static function getMediaModel(): string
    {
        /** @var class-string<Media> */
        $value = config()->string('media.model');

        return $value;
    }

    /**
     * @return class-string<AbstractPathGenerator>
     */
    public static function getDefaultPathGenerator(): string
    {
        /** @var class-string<AbstractPathGenerator> */
        $value = config()->string('media.default_path_generator');

        return $value;
    }

    /**
     * @return string The absolute path of media.temporary_storage_path
     */
    public static function getTemporaryStoragePath(): string
    {
        return storage_path(config()->string('media.temporary_storage_path', 'app/tmp/media'));
    }

    public static function getZipBinaries(): string
    {
        return config()->string('media.zip.zip_binaries', 'zip');
    }
}
