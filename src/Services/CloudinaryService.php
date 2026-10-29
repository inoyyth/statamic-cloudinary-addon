<?php

namespace Inoyyth\StatamicCloudinary\Services;

use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Tag\ImageTag;
use Cloudinary\Transformation\Format;
use Cloudinary\Transformation\Quality;
use Cloudinary\Transformation\Resize;

class CloudinaryService
{
    protected UploadApi $uploadApi;

    public function __construct(?UploadApi $uploadApi = null)
    {
        $this->uploadApi = $uploadApi ?? new UploadApi;
    }

    /**
     * Upload a local file path or stream to Cloudinary
     */
    public function upload(string $filePath, ?string $folder = null, array $options = []): array
    {
        $defaultFolder = $folder ?? config('statamic-cloudinary.upload_folder', 'uploads');

        $defaultOptions = [
            'folder' => $defaultFolder,
            'resource_type' => 'auto',
            'overwrite' => true,
        ];

        return (array) $this->uploadApi->upload($filePath, array_merge($defaultOptions, $options));
    }

    /**
     * Delete an asset from Cloudinary by public ID
     */
    public function delete(string $publicId, string $resourceType = 'image'): array
    {
        return (array) $this->uploadApi->destroy($publicId, [
            'resource_type' => $resourceType,
        ]);
    }

    /**
     * Generate an optimized and transformed image URL
     */
    public function getTransformedUrl(string $publicId, int $width = 400, int $height = 400): string
    {
        return (string) ImageTag::fromParams($publicId)
            ->resize(Resize::fill()->width($width)->height($height))
            ->format(Format::auto())
            ->quality(Quality::auto())
            ->toUrl();
    }
}
