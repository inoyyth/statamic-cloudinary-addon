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
    function cloudinary_transformed_url(mixed $source, array|int $optionsOrWidth = 400, ?int $height = null): string
    {
        return app(CloudinaryService::class)->getTransformedUrl($source, $optionsOrWidth, $height);
    }
}

if (! function_exists('cloudinary_url')) {
    /**
     * Generate a Cloudinary URL with optional transformation options.
     */
    function cloudinary_url(mixed $source, array $options = []): string
    {
        return app(CloudinaryService::class)->url($source, $options);
    }
}

if (! function_exists('cloudinary_image_tag')) {
    /**
     * Generate an HTML <img> tag with transformations and HTML attributes.
     */
    function cloudinary_image_tag(mixed $source, array $transformations = [], array $htmlAttributes = []): string
    {
        return app(CloudinaryService::class)->imageTag($source, $transformations, $htmlAttributes);
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
