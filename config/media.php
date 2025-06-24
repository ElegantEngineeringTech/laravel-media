<?php

declare(strict_types=1);

use Elegantly\Media\Jobs\DeleteModelMediaJob;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\UrlFormatters\DefaultUrlFormatter;

return [
    /**
     * The media model.
     * Define your own model here by extending \Elegantly\Media\Models\Media::class.
     */
    'model' => Media::class,

    /**
     * The MediaConversion model.
     * Define your own model here by extending \Elegantly\Media\Models\MediaConversion::class.
     */
    'media_conversion_model' => MediaConversion::class,

    /**
     * The path used to store temporary file copies for conversions.
     * This will be used with the storage_path() function.
     */
    'temporary_storage_path' => 'app/tmp/media',

    /**
     * The default disk used for storing files.
     */
    'disk' => env('MEDIA_DISK', env('FILESYSTEM_DISK', 'local')),

    /**
     * Determine if media should be deleted with the model
     * when using the HasMedia Trait.
     */
    'delete_media_with_model' => true,

    /**
     * Determine if media should be deleted with the model
     * when it is soft deleted.
     */
    'delete_media_with_trashed_model' => false,

    /**
     * Job class responsible for deleting media when the model is deleted.
     * This helps with performance and monitoring by queuing media deletions.
     */
    'delete_media_with_model_job' => DeleteModelMediaJob::class,

    /**
     * The default collection name assigned media.
     */
    'default_collection_name' => 'default',

    /**
     * The default URL formatter class.
     * Used when calling `$media->getUrl()`.
     */
    'default_url_formatter' => DefaultUrlFormatter::class,

    /**
     * Prefix for the generated file path.
     * Set to null to disable the prefix.
     * Override the generateBasePath method in the Media model for full customization.
     */
    'generated_path_prefix' => null,

    /**
     * Queue connection name to use when dispatching media conversion jobs.
     */
    'queue_connection' => env('QUEUE_CONNECTION', 'sync'),

    /**
     * Queue name to use for media conversion jobs.
     * Set to null to use the default Laravel queue.
     */
    'queue' => null,

    /**
     * Configuration for FFmpeg processing.
     */
    'ffmpeg' => [
        /**
         * The binary path to the FFmpeg executable.
         */
        'ffmpeg_binaries' => env('FFMPEG_BINARIES', 'ffmpeg'),

        /**
         * The binary path to the FFprobe executable.
         */
        'ffprobe_binaries' => env('FFPROBE_BINARIES', 'ffprobe'),

        /**
         * Optional log channel for FFmpeg operations.
         * Set to null to disable logging.
         */
        'log_channel' => null,
    ],
];
