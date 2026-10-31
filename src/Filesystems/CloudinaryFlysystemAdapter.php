<?php

namespace Inoyyth\StatamicCloudinary\Filesystems;

use Cloudinary\Cloudinary;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use Symfony\Component\Mime\MimeTypes;

class CloudinaryFlysystemAdapter implements FilesystemAdapter
{
    protected Cloudinary $cloudinary;

    protected PathPrefixer $prefixer;

    /**
     * In-memory cache for asset metadata to reduce API calls.
     */
    protected array $assetCache = [];

    /**
     * In-memory cache for directory existence.
     */
    protected array $dirCache = [];

    public function __construct(Cloudinary $cloudinary, ?string $prefix = '')
    {
        $this->cloudinary = $cloudinary;
        $this->prefixer = new PathPrefixer($prefix ?? '');
    }

    public function fileExists(string $path): bool
    {
        return $this->getAssetMetadata($path) !== null;
    }

    public function directoryExists(string $path): bool
    {
        $location = trim($path, '/');
        if ($location === '.' || $location === '') {
            return true;
        }

        $prefixedPath = trim($this->prefixer->prefixDirectoryPath($location), '/');
        if ($prefixedPath === '') {
            return true;
        }

        if (isset($this->dirCache[$prefixedPath])) {
            return $this->dirCache[$prefixedPath];
        }

        try {
            $this->cloudinary->adminApi()->subfolders($prefixedPath);

            return $this->dirCache[$prefixedPath] = true;
        } catch (\Throwable) {
            foreach (['image', 'raw', 'video'] as $type) {
                try {
                    $response = $this->cloudinary->adminApi()->assets([
                        'prefix' => $prefixedPath.'/',
                        'resource_type' => $type,
                        'max_results' => 1,
                    ]);

                    if (! empty($response['resources'])) {
                        return $this->dirCache[$prefixedPath] = true;
                    }
                } catch (\Throwable) {
                    // Try next resource type
                }
            }

            return $this->dirCache[$prefixedPath] = false;
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'cld_');
        file_put_contents($tempFile, $contents);

        try {
            $this->writeStream($path, fopen($tempFile, 'rb'), $config);
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $meta = is_resource($contents) ? stream_get_meta_data($contents) : [];
        $filePath = $meta['uri'] ?? null;
        $tempFile = null;

        if (! $filePath || ! file_exists($filePath)) {
            $tempFile = tempnam(sys_get_temp_dir(), 'cld_');
            $dest = fopen($tempFile, 'wb');
            if (is_resource($contents)) {
                stream_copy_to_stream($contents, $dest);
            } else {
                fwrite($dest, (string) $contents);
            }
            fclose($dest);
            $filePath = $tempFile;
        }

        try {
            $prefixedPath = $this->prefixer->prefixPath($path);
            $cleanPrefixedPath = ltrim($prefixedPath, '/');
            $cleanPath = ltrim($path, '/');

            $publicId = $this->getPublicId($cleanPrefixedPath);
            $resourceType = $this->getResourceType($cleanPrefixedPath);

            $result = $this->cloudinary->uploadApi()->upload($filePath, [
                'public_id' => $publicId,
                'overwrite' => true,
                'invalidate' => true,
                'resource_type' => $resourceType,
                'context' => [
                    'path' => $cleanPrefixedPath,
                ],
            ]);

            $resultArray = (array) $result;
            $this->assetCache[$cleanPath] = $resultArray;
            $this->assetCache[$cleanPrefixedPath] = $resultArray;

            $dir = trim(pathinfo($cleanPath, PATHINFO_DIRNAME), '/');
            if ($dir !== '' && $dir !== '.') {
                $this->dirCache[$dir] = true;
            }
        } catch (\Throwable) {
            throw UnableToWriteFile::atLocation($path);
        } finally {
            if ($tempFile && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    public function read(string $path): string
    {
        $url = $this->getUrl($path);
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'follow_location' => 1,
                'header' => "Cache-Control: no-cache\r\nPragma: no-cache\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $contents = @file_get_contents($url, false, $context);
        if ($contents === false) {
            throw UnableToReadFile::fromLocation($path);
        }

        return $contents;
    }

    public function readStream(string $path)
    {
        $url = $this->getUrl($path);
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'follow_location' => 1,
                'header' => "Cache-Control: no-cache\r\nPragma: no-cache\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $stream = @fopen($url, 'rb', false, $context);
        if ($stream === false) {
            throw UnableToReadFile::fromLocation($path);
        }

        return $stream;
    }

    public function delete(string $path): void
    {
        $prefixedPath = $this->prefixer->prefixPath($path);
        $cleanPrefixedPath = ltrim($prefixedPath, '/');
        $cleanPath = ltrim($path, '/');

        $publicId = $this->getPublicId($cleanPrefixedPath);
        $resourceType = $this->getResourceType($cleanPrefixedPath);

        try {
            $this->cloudinary->uploadApi()->destroy($publicId, [
                'invalidate' => true,
                'resource_type' => $resourceType,
            ]);

            unset($this->assetCache[$cleanPath]);
            unset($this->assetCache[$cleanPrefixedPath]);
        } catch (\Throwable $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        $location = trim($path, '/');
        if ($location === '.' || $location === '') {
            return;
        }

        $prefixedPath = trim($this->prefixer->prefixDirectoryPath($location), '/');
        if ($prefixedPath !== '') {
            try {
                $this->cloudinary->adminApi()->deleteFolder($prefixedPath);
            } catch (\Throwable) {
                // Ignore if not found
            }
            unset($this->dirCache[$location]);
            unset($this->dirCache[$prefixedPath]);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        $location = trim($path, '/');
        if ($location === '.' || $location === '') {
            return;
        }

        $prefixedPath = trim($this->prefixer->prefixDirectoryPath($location), '/');
        if ($prefixedPath !== '') {
            try {
                $this->cloudinary->adminApi()->createFolder($prefixedPath);
                $this->dirCache[$location] = true;
                $this->dirCache[$prefixedPath] = true;
            } catch (\Throwable) {
                // Ignore if already exists
            }
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // Assets are managed publicly by default
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, 'public');
    }

    public function mimeType(string $path): FileAttributes
    {
        $meta = $this->getAssetMetadata($path);
        $ext = pathinfo($path, PATHINFO_EXTENSION);

        if ($meta === null) {
            $mime = (new MimeTypes)->getMimeTypes($ext)[0] ?? 'application/octet-stream';

            return new FileAttributes($path, null, 'public', null, $mime);
        }

        $format = $meta['format'] ?? $ext;
        $mime = $format ? ((new MimeTypes)->getMimeTypes($format)[0] ?? 'application/octet-stream') : 'application/octet-stream';

        return new FileAttributes($path, null, 'public', null, $mime);
    }

    public function lastModified(string $path): FileAttributes
    {
        $meta = $this->getAssetMetadata($path);
        if ($meta === null || ! isset($meta['created_at'])) {
            throw UnableToRetrieveMetadata::lastModified($path);
        }

        return new FileAttributes($path, null, null, strtotime($meta['created_at']));
    }

    public function fileSize(string $path): FileAttributes
    {
        $meta = $this->getAssetMetadata($path);
        if ($meta === null) {
            throw UnableToRetrieveMetadata::fileSize($path);
        }

        return new FileAttributes($path, (int) ($meta['bytes'] ?? 0));
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $cleanPath = trim($path, '/');
        if ($cleanPath === '.') {
            $cleanPath = '';
        }

        $prefixedLocation = trim($this->prefixer->prefixDirectoryPath($cleanPath), '/');
        $params = ['max_results' => 500, 'context' => true];

        if ($prefixedLocation !== '') {
            $params['prefix'] = $prefixedLocation.'/';
        }

        // List subfolders
        try {
            $foldersResponse = empty($prefixedLocation)
                ? $this->cloudinary->adminApi()->rootFolders()
                : $this->cloudinary->adminApi()->subfolders($prefixedLocation);

            foreach ($foldersResponse['folders'] ?? [] as $folder) {
                $folderPath = $folder['path'] ?? $folder['name'];
                $strippedDir = $this->prefixer->stripDirectoryPrefix($folderPath);
                $this->dirCache[$strippedDir] = true;
                yield new DirectoryAttributes($strippedDir);
            }
        } catch (\Throwable) {
            // Folders may not exist
        }

        // List assets across image, video, and raw resource types
        foreach (['image', 'video', 'raw'] as $resourceType) {
            $typeParams = array_merge($params, [
                'resource_type' => $resourceType,
            ]);
            $nextCursor = null;

            do {
                try {
                    $currentParams = $typeParams;
                    if ($nextCursor) {
                        $currentParams['next_cursor'] = $nextCursor;
                    }

                    $response = $this->cloudinary->adminApi()->assets($currentParams);
                    $resources = $response['resources'] ?? [];

                    foreach ($resources as $resource) {
                        $customPath = $resource['context']['custom']['path'] ?? null;

                        if ($customPath) {
                            $itemPath = $customPath;
                        } elseif ($resourceType === 'raw') {
                            $itemPath = $resource['public_id'];
                        } else {
                            $format = $resource['format'] ?? '';
                            $itemPath = $resource['public_id'].($format !== '' ? '.'.$format : '');
                        }

                        $relativeItemPath = $this->prefixer->stripPrefix($itemPath);

                        $this->assetCache[ltrim($itemPath, '/')] = (array) $resource;
                        $this->assetCache[ltrim($relativeItemPath, '/')] = (array) $resource;

                        if (! $deep) {
                            $itemDir = trim(pathinfo($relativeItemPath, PATHINFO_DIRNAME), '/');
                            if ($itemDir === '.') {
                                $itemDir = '';
                            }
                            if ($itemDir !== $cleanPath) {
                                continue;
                            }
                        }

                        yield new FileAttributes(
                            $relativeItemPath,
                            $resource['bytes'] ?? 0,
                            'public',
                            isset($resource['created_at']) ? strtotime($resource['created_at']) : null
                        );
                    }

                    $nextCursor = $response['next_cursor'] ?? null;
                } catch (\Throwable) {
                    break;
                }
            } while (! empty($nextCursor));
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $prefixedSource = $this->prefixer->prefixPath($source);
        $prefixedDestination = $this->prefixer->prefixPath($destination);

        $fromId = $this->getPublicId($prefixedSource);
        $toId = $this->getPublicId($prefixedDestination);
        $resourceType = $this->getResourceType($prefixedSource);

        try {
            $this->cloudinary->uploadApi()->rename($fromId, $toId, [
                'overwrite' => true,
                'invalidate' => true,
                'resource_type' => $resourceType,
            ]);

            // Update context with destination path
            try {
                $this->cloudinary->uploadApi()->explicit($toId, [
                    'type' => 'upload',
                    'resource_type' => $resourceType,
                    'context' => [
                        'path' => ltrim($prefixedDestination, '/'),
                    ],
                ]);
            } catch (\Throwable) {
                // Ignore optional context update failure
            }

            unset($this->assetCache[ltrim($source, '/')]);
            unset($this->assetCache[ltrim($destination, '/')]);
            unset($this->assetCache[ltrim($prefixedSource, '/')]);
            unset($this->assetCache[ltrim($prefixedDestination, '/')]);
        } catch (\Throwable $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $stream = $this->readStream($source);
        $this->writeStream($destination, $stream, $config);
        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    public function getUrl(string $path): string
    {
        $prefixedPath = $this->prefixer->prefixPath($path);
        $cleanPath = ltrim($prefixedPath, '/');
        $cloudName = $this->cloudinary->configuration->cloud->cloudName;

        if ($cleanPath === '' || $cleanPath === '.') {
            return "https://res.cloudinary.com/{$cloudName}/image/upload";
        }

        $publicId = $this->getPublicId($cleanPath);
        $extension = strtolower(pathinfo($cleanPath, PATHINFO_EXTENSION));
        $resourceType = $this->getResourceType($cleanPath);

        $target = $extension ? $publicId.'.'.$extension : $publicId;

        return match ($resourceType) {
            'video' => (string) $this->cloudinary->video($target)->toUrl(),
            'raw' => (string) $this->cloudinary->raw($publicId)->toUrl(),
            default => (string) $this->cloudinary->image($target)->toUrl(),
        };
    }

    public function getCloudinary(): Cloudinary
    {
        return $this->cloudinary;
    }

    public function getPrefixer(): PathPrefixer
    {
        return $this->prefixer;
    }

    protected function getPublicId(string $path): string
    {
        $cleanPath = ltrim($path, '/');
        if ($cleanPath === '' || $cleanPath === '.') {
            return '';
        }

        $resourceType = $this->getResourceType($cleanPath);
        if ($resourceType === 'raw') {
            return $cleanPath;
        }

        $dir = pathinfo($cleanPath, PATHINFO_DIRNAME);
        $filename = pathinfo($cleanPath, PATHINFO_FILENAME);

        if ($dir === '.' || $dir === '/' || $dir === '') {
            return $filename;
        }

        return trim($dir, '/').'/'.$filename;
    }

    protected function getResourceType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $imageExtensions = [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif', 'bmp', 'tiff', 'tif', 'ico',
        ];

        $videoExtensions = [
            'mp4', 'webm', 'mov', 'avi', 'wmv', 'flv', 'mkv', 'ogv',
        ];

        if (in_array($extension, $imageExtensions, true)) {
            return 'image';
        }

        if (in_array($extension, $videoExtensions, true)) {
            return 'video';
        }

        return 'raw';
    }

    protected function getAssetMetadata(string $path): ?array
    {
        $cleanRelative = ltrim($path, '/');
        if (isset($this->assetCache[$cleanRelative])) {
            return $this->assetCache[$cleanRelative];
        }

        $prefixedPath = $this->prefixer->prefixPath($path);
        $cleanPath = ltrim($prefixedPath, '/');
        if (isset($this->assetCache[$cleanPath])) {
            return $this->assetCache[$cleanPath];
        }

        $publicId = $this->getPublicId($cleanPath);
        $resourceType = $this->getResourceType($cleanPath);

        try {
            $data = (array) $this->cloudinary->adminApi()->asset($publicId, [
                'resource_type' => $resourceType,
            ]);
            $this->assetCache[$cleanPath] = $data;
            $this->assetCache[$cleanRelative] = $data;

            return $data;
        } catch (\Throwable) {
            if ($resourceType !== 'image') {
                try {
                    $data = (array) $this->cloudinary->adminApi()->asset($publicId);
                    $this->assetCache[$cleanPath] = $data;
                    $this->assetCache[$cleanRelative] = $data;

                    return $data;
                } catch (\Throwable) {
                    return null;
                }
            }

            return null;
        }
    }
}
