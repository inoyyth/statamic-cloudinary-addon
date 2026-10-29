<?php

use Inoyyth\StatamicCloudinary\Services\CloudinaryService;
use Inoyyth\StatamicCloudinary\Services\StatamicImageService;

if (! function_exists('statamic_image_url')) {
    /**
     * Resolve an image URL from various Statamic and standard inputs.
     */
    function statamic_image_url(mixed $img, ?string $fallback = null): string
    {
        return app(StatamicImageService::class)->resolveImageUrl($img, $fallback);
    }
}

if (! function_exists('cloudinary_transformed_url')) {
    /**
     * Generate an optimized and transformed image URL via Cloudinary.
     */
    function cloudinary_transformed_url(string $publicId, int $width = 400, int $height = 400): string
    {
        return app(CloudinaryService::class)->getTransformedUrl($publicId, $width, $height);
    }
}

if (! function_exists('cloudinary_upload')) {
    /**
     * Upload an asset to Cloudinary.
     */
    function cloudinary_upload(string $filePath, ?string $folder = null, array $options = []): array
    {
        return app(CloudinaryService::class)->upload($filePath, $folder, $options);
    }
}
