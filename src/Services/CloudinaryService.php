<?php

namespace Inoyyth\StatamicCloudinary\Services;

use Cloudinary\Api\Admin\AdminApi;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Cloudinary;
use Cloudinary\Transformation\Format;
use Cloudinary\Transformation\Quality;
use Cloudinary\Transformation\Resize;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Fields\Value;

class CloudinaryService
{
    protected ?Cloudinary $cloudinary = null;
    protected ?UploadApi $uploadApi = null;
    protected ?AdminApi $adminApi = null;

    public function __construct(?Cloudinary $cloudinary = null, ?UploadApi $uploadApi = null)
    {
        $this->cloudinary = $cloudinary;
        $this->uploadApi = $uploadApi;
    }

    /**
     * Get or instantiate the Cloudinary instance.
     */
    public function cloudinary(): Cloudinary
    {
        if (! $this->cloudinary) {
            $cloudName = $this->getConfig('statamic-cloudinary.cloud_name', env('CLOUDINARY_CLOUD_NAME'));
            $apiKey = $this->getConfig('statamic-cloudinary.api_key', env('CLOUDINARY_API_KEY'));
            $apiSecret = $this->getConfig('statamic-cloudinary.api_secret', env('CLOUDINARY_API_SECRET'));
            $url = $this->getConfig('statamic-cloudinary.url', env('CLOUDINARY_URL'));
            $secure = (bool) $this->getConfig('statamic-cloudinary.secure', env('CLOUDINARY_SECURE', true));

            if ($url) {
                $this->cloudinary = new Cloudinary($url);
            } else {
                $this->cloudinary = new Cloudinary([
                    'cloud' => [
                        'cloud_name' => $cloudName,
                        'api_key' => $apiKey,
                        'api_secret' => $apiSecret,
                    ],
                    'url' => [
                        'secure' => $secure,
                    ],
                ]);
            }
        }

        return $this->cloudinary;
    }

    /**
     * Get the UploadApi instance.
     */
    public function uploadApi(): UploadApi
    {
        if (! $this->uploadApi) {
            $this->uploadApi = $this->cloudinary()->uploadApi();
        }

        return $this->uploadApi;
    }

    /**
     * Get the AdminApi instance.
     */
    public function adminApi(): AdminApi
    {
        if (! $this->adminApi) {
            $this->adminApi = $this->cloudinary()->adminApi();
        }

        return $this->adminApi;
    }

    /**
     * Upload a local file path or stream to Cloudinary.
     */
    public function upload(string $filePath, ?string $folder = null, array $options = []): array
    {
        $defaultFolder = $folder ?? $this->getConfig('statamic-cloudinary.upload_folder', 'uploads');

        $defaultOptions = [
            'folder' => $defaultFolder,
            'resource_type' => 'auto',
            'overwrite' => true,
        ];

        return (array) $this->uploadApi()->upload($filePath, array_merge($defaultOptions, $options));
    }

    /**
     * Delete an asset from Cloudinary by public ID.
     */
    public function delete(string $publicId, string $resourceType = 'image'): array
    {
        return (array) $this->uploadApi()->destroy($publicId, [
            'resource_type' => $resourceType,
        ]);
    }

    /**
     * Extract public ID from Statamic Asset, Value, array, or URL/string.
     */
    public function extractPublicId(mixed $source): string
    {
        if ($source instanceof Value) {
            $source = $source->value() ?? (string) $source;
        }

        if ($source instanceof AssetContract || (is_object($source) && method_exists($source, 'path'))) {
            $source = (string) $source->path();
        }

        if (is_array($source)) {
            $source = $source['public_id'] ?? $source['path'] ?? $source['url'] ?? reset($source);
        }

        if (! is_string($source) || trim($source) === '') {
            return '';
        }

        $trimmed = trim($source);

        // Remove container prefix (e.g. 'cloudinary::path/to/img.jpg')
        if (str_contains($trimmed, '::')) {
            $parts = explode('::', $trimmed, 2);
            $trimmed = $parts[1] ?? $trimmed;
        }

        // If it is a full Cloudinary URL, extract public ID
        if (preg_match('#/(?:image|video|raw)/(?:upload|authenticated|private)/(?:(?:[a-z]{1,3}_[^/]+(?:,[a-z]{1,3}_[^/]+)*/)*)(?:v\d+/)?(.*?)(?:\.[a-zA-Z0-9]+)?$#i', $trimmed, $matches)) {
            $trimmed = $matches[1] ?? $trimmed;
        }

        return ltrim($trimmed, '/');
    }

    /**
     * Generate an optimized and transformed image URL.
     * Supports either integer width/height or an options array.
     */
    public function getTransformedUrl(mixed $source, array|int $optionsOrWidth = 400, ?int $height = null): string
    {
        $publicId = $this->extractPublicId($source);
        if ($publicId === '') {
            return '';
        }

        $image = $this->cloudinary()->image($publicId);

        if (is_int($optionsOrWidth)) {
            $width = $optionsOrWidth;
            $h = $height ?? $width;
            $image->resize(Resize::fill()->width($width)->height($h));
            $image->format(Format::auto());
            $image->quality(Quality::auto());

            return (string) $image->toUrl();
        }

        $options = $optionsOrWidth;
        if ($height !== null && ! isset($options['height'])) {
            $options['height'] = $height;
        }

        $transformParts = [];

        $crop = $options['crop'] ?? $options['c'] ?? (isset($options['width']) || isset($options['height']) ? 'fill' : null);
        if ($crop) {
            $transformParts[] = 'c_'.$crop;
        }

        $width = $options['width'] ?? $options['w'] ?? null;
        if ($width) {
            $transformParts[] = 'w_'.$width;
        }

        $h = $options['height'] ?? $options['h'] ?? null;
        if ($h) {
            $transformParts[] = 'h_'.$h;
        }

        $gravity = $options['gravity'] ?? $options['g'] ?? null;
        if ($gravity) {
            $transformParts[] = 'g_'.$gravity;
        }

        $aspectRatio = $options['aspect_ratio'] ?? $options['ar'] ?? null;
        if ($aspectRatio) {
            $transformParts[] = 'ar_'.$aspectRatio;
        }

        $quality = $options['quality'] ?? $options['q'] ?? null;
        if ($quality) {
            $transformParts[] = 'q_'.$quality;
        }

        $format = $options['format'] ?? $options['f'] ?? null;
        if ($format) {
            $transformParts[] = 'f_'.$format;
        }

        $effect = $options['effect'] ?? $options['e'] ?? null;
        if ($effect) {
            $transformParts[] = 'e_'.$effect;
        }

        $radius = $options['radius'] ?? $options['r'] ?? null;
        if ($radius) {
            $transformParts[] = 'r_'.$radius;
        }

        $transformString = implode(',', $transformParts);

        return $transformString !== ''
            ? (string) $image->toUrl($transformString)
            : (string) $image->toUrl();
    }

    /**
     * Alias for getTransformedUrl with options array.
     */
    public function url(mixed $source, array $options = []): string
    {
        if (empty($options)) {
            $publicId = $this->extractPublicId($source);
            if ($publicId === '') {
                return '';
            }

            return (string) $this->cloudinary()->image($publicId)->toUrl();
        }

        return $this->getTransformedUrl($source, $options);
    }

    /**
     * Generate an HTML <img> tag with transformations and HTML attributes.
     */
    public function imageTag(mixed $source, array $transformations = [], array $htmlAttributes = []): string
    {
        $url = $this->getTransformedUrl($source, $transformations);
        if ($url === '') {
            return '';
        }

        $attributes = array_merge([
            'src' => $url,
            'alt' => '',
            'loading' => 'lazy',
        ], $htmlAttributes);

        $html = '<img';
        foreach ($attributes as $key => $val) {
            if ($val === null || $val === false) {
                continue;
            }
            if ($val === true) {
                $html .= ' ' . htmlspecialchars((string) $key, ENT_QUOTES);
            } else {
                $html .= ' ' . htmlspecialchars((string) $key, ENT_QUOTES) . '="' . htmlspecialchars((string) $val, ENT_QUOTES) . '"';
            }
        }
        $html .= ' />';

        return $html;
    }

    protected function getConfig(string $key, mixed $default = null): mixed
    {
        if (function_exists('app') && app()->bound('config')) {
            return config($key, $default);
        }

        return $default;
    }
}
