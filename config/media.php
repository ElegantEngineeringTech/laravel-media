<?php

declare(strict_types=1);

use Elegantly\Media\Jobs\DeleteModelMediaJob;
use Elegantly\Media\Models\Media;
use Elegantly\Media\Models\MediaConversion;
use Elegantly\Media\UrlFormatters\DefaultUrlFormatter;

return [
    /**
     * The media model
     * Define your own model here by extending \Elegantly\Media\Models\Media::class
     */
    'model' => Media::class,

    /**
     * The MediaConversion model
     * Define your own model here by extending \Elegantly\Media\Models\MediaConversion::class
     */
    'media_conversion_model' => MediaConversion::class,

    /**
     * The path used to store temporary file copy for conversions
     * This will be used with storage_path() function
     */
    'temporary_storage_path' => 'app/tmp/media',

    /**
     * The default disk used for storing files
     */
    'disk' => env('MEDIA_DISK', env('FILESYSTEM_DISK', 'local')),

    /**
     * Determine if media should be deleted with the model
     * when using the HasMedia Trait
     */
    'delete_media_with_model' => true,

    /**
     * Determine if media should be deleted with the model
     * when it is soft deleted
     */
    'delete_media_with_trashed_model' => false,

    /**
     * Deleting a large number of media attached to a model can be time-consuming
     * or even fail (e.g., cloud API error, permissions, etc.)
     * For performance and monitoring, when a model with the HasMedia trait is deleted,
     * each media is individually deleted inside a job.
     */
    'delete_media_with_model_job' => DeleteModelMediaJob::class,

    /**
     * The default collection name
     */
    'default_collection_name' => 'default',

    /**
     * The default url formatter class, used with `$media->getUrl`
     */
    'default_url_formatter' => DefaultUrlFormatter::class,

    /**
     * Prefix for the generated path of files
     * Set to null if you do not want any prefix
     * To fully customize the generated default path, extend the Media model and override the generateBasePath method
     */
    'generated_path_prefix' => null,

    /**
     * Customize the queue connection used when dispatching conversion jobs
     */
    'queue_connection' => env('QUEUE_CONNECTION', 'sync'),

    /**
     * Customize the queue used when dispatching conversion jobs
     * null will fall back to the default Laravel queue
     */
    'queue' => null,

    'ffmpeg' => env('FFMPEG_BINARIES', 'ffmpeg'),
    'ffprobe' => env('FFPROBE_BINARIES', 'ffprobe'),

];
