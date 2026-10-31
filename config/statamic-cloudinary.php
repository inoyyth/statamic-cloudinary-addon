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
    | 'default_fallback' defines a fallback image path or URL when an image
    | field is empty. Set to null by default so empty fields return an empty
    | string instead of rendering a broken image.
    |
    */
    'default_container' => env('STATAMIC_CLOUDINARY_CONTAINER', 'cloudinary'),
    'default_fallback' => env('STATAMIC_CLOUDINARY_FALLBACK', null),

    /*
    |--------------------------------------------------------------------------
    | Multi-Site Isolation & Root Folder Prefix
    |--------------------------------------------------------------------------
    |
    | When sharing a single Cloudinary account across multiple sites or
    | environments, set a prefix or folder to scope all container assets to
    | that directory (e.g. 'site-production' or 'sites/brand-a').
    |
    */
    'prefix' => env('CLOUDINARY_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Upload Settings
    |--------------------------------------------------------------------------
    |
    | Default folder and resource options used by CloudinaryService direct uploads.
    |
    */
    'upload_folder' => env('STATAMIC_CLOUDINARY_UPLOAD_FOLDER', 'uploads'),
];
