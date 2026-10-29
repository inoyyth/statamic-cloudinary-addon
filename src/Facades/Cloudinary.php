<?php

namespace Inoyyth\StatamicCloudinary\Facades;

use Illuminate\Support\Facades\Facade;
use Inoyyth\StatamicCloudinary\Services\CloudinaryService;

/**
 * @method static array upload(string $filePath, ?string $folder = null, array $options = [])
 * @method static array delete(string $publicId, string $resourceType = 'image')
 * @method static string getTransformedUrl(string $publicId, int $width = 400, int $height = 400)
 *
 * @see CloudinaryService
 */
class Cloudinary extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CloudinaryService::class;
    }
}
