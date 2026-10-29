<?php

namespace Inoyyth\StatamicCloudinary\Filesystems;

use Cloudinary\Cloudinary;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use Symfony\Component\Mime\MimeTypes;

class CloudinaryFlysystemAdapter implements FilesystemAdapter
{
    protected Cloudinary $cloudinary;

    /**
     * In-memory cache for asset metadata to reduce API calls.
     */
    protected array $assetCache = [];

    /**
     * In-memory cache for directory existence.
     */
    protected array $dirCache = [];

    public function __construct(Cloudinary $cloudinary)
    {
        $this->cloudinary = $cloudinary;
    }

    public function fileExists(string $path): bool
    {
        return $this->getAssetMetadata($path) !== null;
    }

    public function directoryExists(string $path): bool
    {
        $path = trim($path, '/.');
        if ($path === '') {
            return true;
        }

        if (isset($this->dirCache[$path])) {
            return $this->dirCache[$path];
        }

        try {
            $this->cloudinary->adminApi()->subfolders($path);

            return $this->dirCache[$path] = true;
        } catch (\Throwable) {
            try {
                $response = $this->cloudinary->adminApi()->assets([
                    'prefix' => $path.'/',
                    'max_results' => 1,
                ]);

                return $this->dirCache[$path] = ! empty($response['resources']);
            } catch (\Throwable) {
                return $this->dirCache[$path] = false;
            }
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
            $publicId = $this->getPublicId($path);
            $resourceType = $this->getResourceType($path);

            $result = $this->cloudinary->uploadApi()->upload($filePath, [
                'public_id' => $publicId,
                'overwrite' => true,
                'resource_type' => $resourceType,
            ]);

            $cleanPath = ltrim($path, '/');
            $this->assetCache[$cleanPath] = (array) $result;

            $dir = trim(pathinfo($cleanPath, PATHINFO_DIRNAME), '/.');
            if ($dir !== '') {
                $this->dirCache[$dir] = true;
            }
        } catch (\Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
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
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
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
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
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
        $publicId = $this->getPublicId($path);
        $resourceType = $this->getResourceType($path);

        try {
            $this->cloudinary->uploadApi()->destroy($publicId, [
                'invalidate' => true,
                'resource_type' => $resourceType,
            ]);

            unset($this->assetCache[ltrim($path, '/')]);
        } catch (\Throwable $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        $path = trim($path, '/.');
        if ($path !== '') {
            try {
                $this->cloudinary->adminApi()->deleteFolder($path);
            } catch (\Throwable) {
                // Ignore if not found
            }
            unset($this->dirCache[$path]);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        $path = trim($path, '/.');
        if ($path !== '') {
            try {
                $this->cloudinary->adminApi()->createFolder($path);
                $this->dirCache[$path] = true;
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
        if ($meta === null) {
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            $mime = (new MimeTypes)->getMimeTypes($ext)[0] ?? 'application/octet-stream';

            return new FileAttributes($path, null, 'public', null, $mime);
        }

        $format = $meta['format'] ?? null;
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
        $prefix = trim($path, '/.');
        $params = ['max_results' => 500];

        if (! empty($prefix)) {
            $params['prefix'] = $prefix;
        }

        // List subfolders
        try {
            $foldersResponse = empty($prefix)
                ? $this->cloudinary->adminApi()->rootFolders()
                : $this->cloudinary->adminApi()->subfolders($prefix);

            foreach ($foldersResponse['folders'] ?? [] as $folder) {
                $folderPath = $folder['path'] ?? $folder['name'];
                $this->dirCache[$folderPath] = true;
                yield new DirectoryAttributes($folderPath);
            }
        } catch (\Throwable) {
            // Folders may not exist
        }

        // List assets with pagination cursor support
        do {
            try {
                $response = $this->cloudinary->adminApi()->assets($params);
                $resources = $response['resources'] ?? [];

                foreach ($resources as $resource) {
                    $format = $resource['format'] ?? '';
                    $itemPath = $resource['public_id'].($format ? '.'.$format : '');

                    $this->assetCache[$itemPath] = (array) $resource;

                    yield new FileAttributes(
                        $itemPath,
                        $resource['bytes'] ?? 0,
                        'public',
                        isset($resource['created_at']) ? strtotime($resource['created_at']) : null
                    );
                }

                $nextCursor = $response['next_cursor'] ?? null;
                if ($nextCursor) {
                    $params['next_cursor'] = $nextCursor;
                }
            } catch (\Throwable) {
                break;
            }
        } while (! empty($nextCursor));
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $fromId = $this->getPublicId($source);
        $toId = $this->getPublicId($destination);
        $resourceType = $this->getResourceType($source);

        try {
            $this->cloudinary->uploadApi()->rename($fromId, $toId, [
                'overwrite' => true,
                'resource_type' => $resourceType,
            ]);

            unset($this->assetCache[ltrim($source, '/')]);
            unset($this->assetCache[ltrim($destination, '/')]);
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
        $cleanPath = trim($path, '/.');
        $cloudName = $this->cloudinary->configuration->cloud->cloudName;

        if ($cleanPath === '') {
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

    protected function getPublicId(string $path): string
    {
        $dir = pathinfo($path, PATHINFO_DIRNAME);
        $filename = pathinfo($path, PATHINFO_FILENAME);

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
        $path = ltrim($path, '/');
        if (isset($this->assetCache[$path])) {
            return $this->assetCache[$path];
        }

        $publicId = $this->getPublicId($path);
        $resourceType = $this->getResourceType($path);

        try {
            $data = (array) $this->cloudinary->adminApi()->asset($publicId, [
                'resource_type' => $resourceType,
            ]);
            $this->assetCache[$path] = $data;

            return $data;
        } catch (\Throwable) {
            if ($resourceType !== 'image') {
                try {
                    $data = (array) $this->cloudinary->adminApi()->asset($publicId);
                    $this->assetCache[$path] = $data;

                    return $data;
                } catch (\Throwable) {
                    return null;
                }
            }

            return null;
        }
    }
}
