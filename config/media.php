<?php

// config for Finller/Media

use Finller\Media\Models\Media;

return [
    /**
     * The media model
     */
    'model' => Media::class,

    /**
     * The default disk used to store files
     */
    'disk' => env('MEDIA_DISK', env('FILESYSTEM_DISK', 'local')),

    /**
     * The default collection name
     */
    'default_collection_name' => 'default',

    /**
     * Prefix the generate path of files
     * set to null if you don't want any prefix
     * To fully customize the generated default path, extends the Media model ans override generateBasePath method
     */
    'generated_path_prefix' => null,
];
