<?php

namespace Inoyyth\StatamicCloudinary\Modifiers;

use Inoyyth\StatamicCloudinary\Services\CloudinaryService;
use Inoyyth\StatamicCloudinary\Services\StatamicImageService;
use Statamic\Modifiers\Modifier;

class CloudinaryModifier extends Modifier
{
    protected static $aliases = ['cloudinary_url', 'statamic_image'];

    /**
     * Modify a value.
     */
    public function index($value, array $params = [], array $context = []): string
    {
        $fallback = $params[0] ?? null;

        return app(StatamicImageService::class)->resolveImageUrl($value, $fallback);
    }

    /**
     * The {{ image | cloudinary:transform(width, height) }} modifier.
     */
    public function transform($value, array $params = [], array $context = []): string
    {
        $width = isset($params[0]) ? (int) $params[0] : 400;
        $height = isset($params[1]) ? (int) $params[1] : 400;

        return app(CloudinaryService::class)->getTransformedUrl((string) $value, $width, $height);
    }
}
