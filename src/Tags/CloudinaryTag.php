<?php

namespace Inoyyth\StatamicCloudinary\Tags;

use Inoyyth\StatamicCloudinary\Services\CloudinaryService;
use Inoyyth\StatamicCloudinary\Services\StatamicImageService;
use Statamic\Tags\Tags;

class CloudinaryTag extends Tags
{
    protected static $handle = 'cloudinary';

    /**
     * The {{ cloudinary }} tag.
     */
    public function index()
    {
        return $this->url();
    }

    /**
     * The {{ cloudinary:url }} tag.
     * Resolves an asset or string URL.
     */
    public function url(): string
    {
        $src = $this->params->get(['src', 'path', 'id', 'image']) ?? $this->context->get('src');
        $fallback = $this->params->get('fallback');

        return app(StatamicImageService::class)->resolveImageUrl($src, $fallback);
    }

    /**
     * The {{ cloudinary:transform }} tag.
     * Generates a transformed URL with width, height, crop, quality, format, gravity, etc.
     */
    public function transform(): string
    {
        $src = $this->params->get(['src', 'public_id', 'id', 'path', 'image']) ?? $this->context->get('src');
        $options = $this->params->all();

        // Remove tag-specific lookup params from transformation options
        unset($options['src'], $options['public_id'], $options['id'], $options['path'], $options['image']);

        return app(CloudinaryService::class)->getTransformedUrl($src, $options);
    }

    /**
     * The {{ cloudinary:image }} or {{ cloudinary:tag }} tag.
     * Generates an HTML <img> tag with transformations and HTML attributes.
     */
    public function image(): string
    {
        $src = $this->params->get(['src', 'public_id', 'id', 'path', 'image']) ?? $this->context->get('src');
        $all = $this->params->all();

        $transformKeys = [
            'width', 'height', 'crop', 'quality', 'format',
            'gravity', 'aspect_ratio', 'effect', 'radius',
            'fetch_format', 'w', 'h', 'c', 'q', 'f', 'g', 'ar',
        ];
        $transformations = [];
        $htmlAttributes = [];

        foreach ($all as $key => $value) {
            if (in_array($key, ['src', 'public_id', 'id', 'path', 'image'], true)) {
                continue;
            }
            if (in_array(strtolower($key), $transformKeys, true)) {
                $transformations[$key] = $value;
            } else {
                $htmlAttributes[$key] = $value;
            }
        }

        return app(CloudinaryService::class)->imageTag($src, $transformations, $htmlAttributes);
    }

    /**
     * Alias for image().
     */
    public function tag(): string
    {
        return $this->image();
    }
}
