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
];
