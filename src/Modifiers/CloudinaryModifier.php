<?php

namespace Inoyyth\StatamicCloudinary\Modifiers;

use Inoyyth\StatamicCloudinary\Services\CloudinaryService;
use Inoyyth\StatamicCloudinary\Services\StatamicImageService;
use Statamic\Modifiers\Modifier;

class CloudinaryModifier extends Modifier
{
    protected static $aliases = [
        'cloudinary_url',
        'cloudinary_transform',
        'statamic_image',
    ];

    /**
     * Modify a value into a resolved URL.
     */
    public function index($value, array $params = [], array $context = []): string
    {
        $fallback = $params[0] ?? null;

        return app(StatamicImageService::class)->resolveImageUrl($value, $fallback);
    }

    /**
     * Transform an image modifier: {{ image | cloudinary:transform(800, 600, 'fill', 'auto', 'webp') }}
     */
    public function transform($value, array $params = [], array $context = []): string
    {
        $options = [];

        if (isset($params[0]) && $params[0] !== '') {
            $options['width'] = (int) $params[0];
        }
        if (isset($params[1]) && $params[1] !== '') {
            $options['height'] = (int) $params[1];
        }
        if (isset($params[2]) && $params[2] !== '') {
            $options['crop'] = (string) $params[2];
        }
        if (isset($params[3]) && $params[3] !== '') {
            $options['quality'] = (string) $params[3];
        }
        if (isset($params[4]) && $params[4] !== '') {
            $options['format'] = (string) $params[4];
        }

        if (empty($options)) {
            $options = ['width' => 400, 'height' => 400];
        }

        return app(CloudinaryService::class)->getTransformedUrl($value, $options);
    }
}
