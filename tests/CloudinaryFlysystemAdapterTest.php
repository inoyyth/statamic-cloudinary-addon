<?php

namespace Inoyyth\StatamicCloudinary\Tests;

use Cloudinary\Api\Admin\AdminApi;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Cloudinary;
use Inoyyth\StatamicCloudinary\Filesystems\CloudinaryFlysystemAdapter;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CloudinaryFlysystemAdapterTest extends TestCase
{
    protected CloudinaryFlysystemAdapter $adapter;

    protected Cloudinary $cloudinary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => 'test_cloud',
                'api_key' => '1234567890',
                'api_secret' => 'abcdefghij',
            ],
            'url' => [
                'secure' => true,
            ],
        ]);

        $this->adapter = new CloudinaryFlysystemAdapter($this->cloudinary);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_public_id_handles_filenames_and_paths(): void
    {
        $method = new ReflectionMethod($this->adapter, 'getPublicId');

        $this->assertEquals('image', $method->invoke($this->adapter, 'image.jpg'));
        $this->assertEquals('folder/image', $method->invoke($this->adapter, 'folder/image.png'));
        $this->assertEquals('deep/nested/folder/file', $method->invoke($this->adapter, 'deep/nested/folder/file.webp'));
        $this->assertEquals('image', $method->invoke($this->adapter, '/image.jpg'));
    }

    public function test_get_public_id_preserves_extensions_for_raw_and_meta_files(): void
    {
        $method = new ReflectionMethod($this->adapter, 'getPublicId');

        $this->assertEquals('document.pdf', $method->invoke($this->adapter, 'document.pdf'));
        $this->assertEquals('.meta/photo.jpg.yaml', $method->invoke($this->adapter, '.meta/photo.jpg.yaml'));
        $this->assertEquals('.meta/photo.jpeg.yaml', $method->invoke($this->adapter, '.meta/photo.jpeg.yaml'));
        $this->assertEquals('folder/.meta/photo.jpg.yaml', $method->invoke($this->adapter, 'folder/.meta/photo.jpg.yaml'));
        $this->assertEquals('archive.zip', $method->invoke($this->adapter, 'archive.zip'));
    }

    public function test_get_resource_type(): void
    {
        $method = new ReflectionMethod($this->adapter, 'getResourceType');

        $this->assertEquals('image', $method->invoke($this->adapter, 'photo.jpg'));
        $this->assertEquals('image', $method->invoke($this->adapter, 'photo.jpeg'));
        $this->assertEquals('image', $method->invoke($this->adapter, 'vector.svg'));
        $this->assertEquals('video', $method->invoke($this->adapter, 'movie.mp4'));
        $this->assertEquals('raw', $method->invoke($this->adapter, 'document.pdf'));
        $this->assertEquals('raw', $method->invoke($this->adapter, '.meta/photo.jpg.yaml'));
    }

    public function test_get_url_for_base_and_files(): void
    {
        $this->assertEquals('https://res.cloudinary.com/test_cloud/image/upload', $this->adapter->getUrl(''));
        $this->assertEquals('https://res.cloudinary.com/test_cloud/image/upload', $this->adapter->getUrl('/'));

        $imageUrl = $this->adapter->getUrl('banner.jpg');
        $this->assertStringContainsString('https://res.cloudinary.com/test_cloud/image/upload/banner.jpg', $imageUrl);
    }

    public function test_get_url_preserves_leading_dots_and_raw_extensions(): void
    {
        $metaUrl = $this->adapter->getUrl('.meta/photo.jpg.yaml');
        $this->assertStringContainsString('/raw/upload/', $metaUrl);
        $this->assertStringContainsString('/.meta/photo.jpg.yaml', $metaUrl);
        $this->assertStringNotContainsString('meta/photo.jpg.jpg', $metaUrl);

        $pdfUrl = $this->adapter->getUrl('document.pdf');
        $this->assertStringContainsString('/raw/upload/', $pdfUrl);
        $this->assertStringContainsString('/document.pdf', $pdfUrl);

        $videoUrl = $this->adapter->getUrl('video.mp4');
        $this->assertStringContainsString('/video/upload/', $videoUrl);
        $this->assertStringContainsString('/video.mp4', $videoUrl);

        $jpegUrl = $this->adapter->getUrl('photo.jpeg');
        $this->assertStringContainsString('/image/upload/', $jpegUrl);
        $this->assertStringContainsString('/photo.jpeg', $jpegUrl);
    }

    public function test_write_uploads_with_invalidate_context_and_correct_public_id(): void
    {
        $uploadApi = Mockery::mock(UploadApi::class);
        $cloudinary = Mockery::mock(Cloudinary::class);
        $cloudinary->shouldReceive('uploadApi')->andReturn($uploadApi);

        $uploadApi->shouldReceive('upload')
            ->once()
            ->with(
                Mockery::type('string'),
                Mockery::on(function ($options) {
                    return $options['public_id'] === '.meta/photo.jpeg.yaml'
                        && $options['invalidate'] === true
                        && $options['resource_type'] === 'raw'
                        && $options['overwrite'] === true
                        && ($options['context']['path'] ?? null) === '.meta/photo.jpeg.yaml';
                })
            )
            ->andReturn(['public_id' => '.meta/photo.jpeg.yaml']);

        $uploadApi->shouldReceive('upload')
            ->once()
            ->with(
                Mockery::type('string'),
                Mockery::on(function ($options) {
                    return $options['public_id'] === 'photo'
                        && $options['invalidate'] === true
                        && $options['resource_type'] === 'image'
                        && ($options['context']['path'] ?? null) === 'photo.jpeg';
                })
            )
            ->andReturn(['public_id' => 'photo', 'format' => 'jpg']);

        $adapter = new CloudinaryFlysystemAdapter($cloudinary);

        $adapter->write('.meta/photo.jpeg.yaml', 'alt: Sample photo', new Config);
        $adapter->write('photo.jpeg', 'fake-image-bytes', new Config);

        $this->assertTrue(true);
    }

    public function test_list_contents_queries_all_resource_types_and_preserves_custom_paths(): void
    {
        $adminApi = Mockery::mock(AdminApi::class);
        $cloudinary = Mockery::mock(Cloudinary::class);
        $cloudinary->shouldReceive('adminApi')->andReturn($adminApi);

        $adminApi->shouldReceive('rootFolders')->andReturn(['folders' => []]);

        // 1. Image listing: returns photo.jpeg (via context) and hero.jpg
        $adminApi->shouldReceive('assets')
            ->once()
            ->with(Mockery::on(fn ($p) => ($p['resource_type'] ?? '') === 'image'))
            ->andReturn([
                'resources' => [
                    [
                        'public_id' => 'photo',
                        'format' => 'jpg',
                        'bytes' => 1234,
                        'context' => ['custom' => ['path' => 'photo.jpeg']],
                    ],
                    [
                        'public_id' => 'hero',
                        'format' => 'jpg',
                        'bytes' => 5678,
                    ],
                ],
            ]);

        // 2. Video listing: returns video.mp4
        $adminApi->shouldReceive('assets')
            ->once()
            ->with(Mockery::on(fn ($p) => ($p['resource_type'] ?? '') === 'video'))
            ->andReturn([
                'resources' => [
                    [
                        'public_id' => 'video',
                        'format' => 'mp4',
                        'bytes' => 9999,
                    ],
                ],
            ]);

        // 3. Raw listing: returns document.pdf and .meta files
        $adminApi->shouldReceive('assets')
            ->once()
            ->with(Mockery::on(fn ($p) => ($p['resource_type'] ?? '') === 'raw'))
            ->andReturn([
                'resources' => [
                    [
                        'public_id' => 'document.pdf',
                        'bytes' => 4321,
                    ],
                    [
                        'public_id' => '.meta/photo.jpeg.yaml',
                        'bytes' => 50,
                    ],
                    [
                        'public_id' => '.meta/hero.jpg.yaml',
                        'bytes' => 45,
                    ],
                ],
            ]);

        $adapter = new CloudinaryFlysystemAdapter($cloudinary);

        $results = iterator_to_array($adapter->listContents('', true));
        $paths = array_map(fn (FileAttributes $attr) => $attr->path(), $results);

        $this->assertContains('photo.jpeg', $paths);
        $this->assertContains('hero.jpg', $paths);
        $this->assertContains('video.mp4', $paths);
        $this->assertContains('document.pdf', $paths);
        $this->assertContains('.meta/photo.jpeg.yaml', $paths);
        $this->assertContains('.meta/hero.jpg.yaml', $paths);
    }

    public function test_prefix_scoping(): void
    {
        $adapter = new CloudinaryFlysystemAdapter($this->cloudinary, 'site-production');

        $imageUrl = $adapter->getUrl('banner.jpg');
        $this->assertStringContainsString('site-production/banner.jpg', $imageUrl);

        $metaUrl = $adapter->getUrl('.meta/banner.jpg.yaml');
        $this->assertStringContainsString('site-production/.meta/banner.jpg.yaml', $metaUrl);
    }
}
