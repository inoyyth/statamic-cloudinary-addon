<?php

namespace Inoyyth\StatamicCloudinary\Facades;

use Illuminate\Support\Facades\Facade;
use Inoyyth\StatamicCloudinary\Services\StatamicImageService;

/**
 * @method static string resolveImageUrl(mixed $img, ?string $fallback = null)
 * @method static string resolve(mixed $img, ?string $fallback = null)
 * @method static string url(mixed $img, ?string $fallback = null)
 *
 * @see StatamicImageService
 */
class StatamicImage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return StatamicImageService::class;
    }
}
