<?php

use ElegantEngineeringTech\Media\Jobs\DeleteModelMediaJob;
use ElegantEngineeringTech\Media\Models\Media;

return [
    /**
     * The media model
     * Define your own model here by extending \ElegantEngineeringTech\Media\Models\Media::class
     */
    'model' => Media::class,

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

    /**
     * Customize WithoutOverlapping middleware settings
     */
    'queue_overlapping' => [
        /**
         * The release value should be longer than the longest conversion job that might run
         * Default is: 1 minute. Increase it if your jobs are longer.
         */
        'release_after' => 60,
        /**
         * The expire value allows you to forget a lock in case of an unexpected job failure
         *
         * @see https://laravel.com/docs/10.x/queues#preventing-job-overlaps
         */
        'expire_after' => 60 * 60,
    ],

];
