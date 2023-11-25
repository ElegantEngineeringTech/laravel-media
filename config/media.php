<?php

// config for Finller/LaravelMedia
return [
    'disk' => env('MEDIA_DISK', env('FILESYSTEM_DISK', 'local')),
    'default_collection_name' => 'default',
];
