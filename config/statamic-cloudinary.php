<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cloudinary Credentials
    |--------------------------------------------------------------------------
    |
    | Credentials can be loaded directly from your environment variables
    | or CLOUDINARY_URL connection string.
    |
    */
    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
    'api_key' => env('CLOUDINARY_API_KEY'),
    'api_secret' => env('CLOUDINARY_API_SECRET'),
    'url' => env('CLOUDINARY_URL'),
    'secure' => env('CLOUDINARY_SECURE', true),

    /*
    |--------------------------------------------------------------------------
    | Default Asset Container & Fallback
    |--------------------------------------------------------------------------
    |
    | When resolving images without an explicit container or fallback path,
    | these default settings will be used.
    |
    */
    'default_container' => env('STATAMIC_CLOUDINARY_CONTAINER', 'cloudinary'),
    'default_fallback' => env('STATAMIC_CLOUDINARY_FALLBACK', 'images/resume_banner_hero.jpg'),

    /*
    |--------------------------------------------------------------------------
    | Upload Settings
    |--------------------------------------------------------------------------
    |
    | Default folder and resource options used by CloudinaryService.
    |
    */
    'upload_folder' => env('STATAMIC_CLOUDINARY_UPLOAD_FOLDER', 'uploads'),
];
