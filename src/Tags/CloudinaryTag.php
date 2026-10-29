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
        $src = $this->params->get(['src', 'path', 'id', 'image']);
        $fallback = $this->params->get('fallback');

        return app(StatamicImageService::class)->resolveImageUrl($src, $fallback);
    }

    /**
     * The {{ cloudinary:transform }} tag.
     * Generates a transformed URL with width & height.
     */
    public function transform(): string
    {
        $src = (string) $this->params->get(['src', 'public_id', 'id']);
        $width = (int) $this->params->get('width', 400);
        $height = (int) $this->params->get('height', 400);

        return app(CloudinaryService::class)->getTransformedUrl($src, $width, $height);
    }
}
