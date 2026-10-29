<?php

namespace Inoyyth\StatamicCloudinary\Tests;

use Cloudinary\Cloudinary;
use Inoyyth\StatamicCloudinary\Filesystems\CloudinaryFlysystemAdapter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CloudinaryFlysystemAdapterTest extends TestCase
{
    protected CloudinaryFlysystemAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        $cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => 'test_cloud',
                'api_key' => '1234567890',
                'api_secret' => 'abcdefghij',
            ],
        ]);

        $this->adapter = new CloudinaryFlysystemAdapter($cloudinary);
    }

    public function test_get_public_id_handles_filenames_and_paths(): void
    {
        $method = new ReflectionMethod($this->adapter, 'getPublicId');

        $this->assertEquals('image', $method->invoke($this->adapter, 'image.jpg'));
        $this->assertEquals('folder/image', $method->invoke($this->adapter, 'folder/image.png'));
        $this->assertEquals('deep/nested/folder/file', $method->invoke($this->adapter, 'deep/nested/folder/file.webp'));
        $this->assertEquals('image', $method->invoke($this->adapter, '/image.jpg'));
    }

    public function test_get_resource_type(): void
    {
        $method = new ReflectionMethod($this->adapter, 'getResourceType');

        $this->assertEquals('image', $method->invoke($this->adapter, 'photo.jpg'));
        $this->assertEquals('image', $method->invoke($this->adapter, 'vector.svg'));
        $this->assertEquals('video', $method->invoke($this->adapter, 'movie.mp4'));
        $this->assertEquals('raw', $method->invoke($this->adapter, 'document.pdf'));
    }

    public function test_get_url_for_base_and_files(): void
    {
        $this->assertEquals('https://res.cloudinary.com/test_cloud/image/upload', $this->adapter->getUrl(''));
        $this->assertEquals('https://res.cloudinary.com/test_cloud/image/upload', $this->adapter->getUrl('/'));

        $imageUrl = $this->adapter->getUrl('banner.jpg');
        $this->assertStringContainsString('https://res.cloudinary.com/test_cloud/image/upload/banner.jpg', $imageUrl);
    }
}
