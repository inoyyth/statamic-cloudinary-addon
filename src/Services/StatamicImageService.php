<?php

namespace Inoyyth\StatamicCloudinary\Services;

use Illuminate\Support\Collection;
use Statamic\Assets\AssetCollection;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Facades\Asset;
use Statamic\Fields\Value;

class StatamicImageService
{
    public const DEFAULT_FALLBACK = 'images/resume_banner_hero.jpg';

    /**
     * Resolve an image URL from various Statamic and standard inputs.
     */
    public function resolveImageUrl(mixed $img, ?string $fallback = null): string
    {
        $defaultFallback = $this->getConfig('statamic-cloudinary.default_fallback', self::DEFAULT_FALLBACK);
        $fallback = $fallback ?? $defaultFallback;
        $container = $this->getConfig('statamic-cloudinary.default_container', 'cloudinary');

        // 1. Statamic augmented Value object
        if ($img instanceof Value) {
            $unwrapped = $img->value();
            if ($unwrapped) {
                return $this->resolveImageUrl($unwrapped, $fallback);
            }

            $stringVal = (string) $img;
            if ($this->isAbsoluteUrl($stringVal)) {
                return $stringVal;
            }
        }

        // 2. Statamic Asset object
        if ($img instanceof AssetContract || (is_object($img) && method_exists($img, 'url'))) {
            return (string) $img->url();
        }

        // 3. AssetCollection or Laravel Collection
        if ($img instanceof AssetCollection || $img instanceof Collection) {
            $first = $img->first();

            return $first ? $this->resolveImageUrl($first, $fallback) : $this->fallbackUrl($fallback);
        }

        // 4. Array (e.g. nested asset array or array of asset paths)
        if (is_array($img) && ! empty($img)) {
            if (isset($img['url']) && is_string($img['url'])) {
                return $img['url'];
            }

            $first = reset($img);

            return $first ? $this->resolveImageUrl($first, $fallback) : $this->fallbackUrl($fallback);
        }

        // 5. String (URL, container asset path, or asset ID)
        if (is_string($img) && trim($img) !== '') {
            $trimmed = trim($img);

            // Direct URL
            if ($this->isAbsoluteUrl($trimmed)) {
                return $trimmed;
            }

            // Find in Statamic Assets (e.g. by path or container ID)
            try {
                $foundAsset = Asset::find($trimmed)
                    ?? Asset::find($container.'::'.$trimmed)
                    ?? Asset::query()->where('path', $trimmed)->first()
                    ?? Asset::query()->where('container', $container)->where('path', $trimmed)->first();

                if ($foundAsset) {
                    return (string) $foundAsset->url();
                }
            } catch (\Throwable) {
                // If Asset facade or DB is not booted in unit tests
            }

            // Fallback to local asset if helper exists
            if (function_exists('asset')) {
                try {
                    return asset($trimmed);
                } catch (\Throwable) {
                    return $trimmed;
                }
            }

            return $trimmed;
        }

        return $this->fallbackUrl($fallback);
    }

    /**
     * Alias for resolveImageUrl.
     */
    public function resolve(mixed $img, ?string $fallback = null): string
    {
        return $this->resolveImageUrl($img, $fallback);
    }

    /**
     * Concise alias for resolveImageUrl.
     */
    public function url(mixed $img, ?string $fallback = null): string
    {
        return $this->resolveImageUrl($img, $fallback);
    }

    /**
     * Determine if the string is an absolute URL or protocol-relative URL.
     */
    protected function isAbsoluteUrl(string $url): bool
    {
        return str_starts_with($url, 'http://')
            || str_starts_with($url, 'https://')
            || str_starts_with($url, '//');
    }

    /**
     * Format the fallback URL.
     */
    protected function fallbackUrl(?string $fallback): string
    {
        if (! $fallback) {
            return '';
        }

        if ($this->isAbsoluteUrl($fallback)) {
            return $fallback;
        }

        if (function_exists('asset')) {
            try {
                return asset($fallback);
            } catch (\Throwable) {
                return $fallback;
            }
        }

        return $fallback;
    }

    /**
     * Safely retrieve config with fallback if container is not bound.
     */
    protected function getConfig(string $key, mixed $default = null): mixed
    {
        if (function_exists('app') && app()->bound('config')) {
            return config($key, $default);
        }

        return $default;
    }
}
