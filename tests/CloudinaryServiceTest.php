<?php

namespace Inoyyth\StatamicCloudinary\Tests;

use Cloudinary\Cloudinary;
use Inoyyth\StatamicCloudinary\Services\CloudinaryService;
use PHPUnit\Framework\TestCase;
use Statamic\Fields\Value;

class CloudinaryServiceTest extends TestCase
{
    protected CloudinaryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => 'demo_cloud',
                'api_key' => '1234567890',
                'api_secret' => 'abcdefghij',
            ],
            'url' => [
                'secure' => true,
            ],
        ]);

        $this->service = new CloudinaryService($cloudinary);
    }

    public function test_service_can_instantiate_and_expose_apis(): void
    {
        $this->assertInstanceOf(CloudinaryService::class, $this->service);
        $this->assertInstanceOf(Cloudinary::class, $this->service->cloudinary());
    }

    public function test_extract_public_id_from_various_inputs(): void
    {
        // Simple name
        $this->assertEquals('sample', $this->service->extractPublicId('sample'));
        $this->assertEquals('sample.jpg', $this->service->extractPublicId('sample.jpg'));

        // Nested folder
        $this->assertEquals('banners/hero.jpg', $this->service->extractPublicId('banners/hero.jpg'));
        $this->assertEquals('banners/hero.jpg', $this->service->extractPublicId('/banners/hero.jpg'));

        // Statamic container prefix
        $this->assertEquals('banners/hero.jpg', $this->service->extractPublicId('cloudinary::banners/hero.jpg'));

        // Full Cloudinary URLs
        $url1 = 'https://res.cloudinary.com/demo/image/upload/sample.jpg';
        $this->assertEquals('sample', $this->service->extractPublicId($url1));

        $url2 = 'https://res.cloudinary.com/demo/image/upload/v1612345678/folder/nested.png';
        $this->assertEquals('folder/nested', $this->service->extractPublicId($url2));

        $url3 = 'https://res.cloudinary.com/demo/image/upload/w_800,h_600,c_fill/v123456/photos/pic.webp';
        $this->assertEquals('photos/pic', $this->service->extractPublicId($url3));

        // Statamic Value object
        $value = new Value('cloudinary::portfolio/project.jpg');
        $this->assertEquals('portfolio/project.jpg', $this->service->extractPublicId($value));

        // Array input
        $this->assertEquals('avatar', $this->service->extractPublicId(['public_id' => 'avatar']));
        $this->assertEquals('photo', $this->service->extractPublicId(['url' => 'https://res.cloudinary.com/demo/image/upload/photo.jpg']));

        // Empty input
        $this->assertEquals('', $this->service->extractPublicId(''));
        $this->assertEquals('', $this->service->extractPublicId(null));
    }

    public function test_get_transformed_url_with_width_and_height(): void
    {
        $url = $this->service->getTransformedUrl('sample_image', 800, 400);

        $this->assertStringContainsString('sample_image', $url);
        $this->assertStringContainsString('w_800', $url);
        $this->assertStringContainsString('h_400', $url);
    }

    public function test_get_transformed_url_with_array_options(): void
    {
        $url = $this->service->getTransformedUrl('sample_image', [
            'width' => 600,
            'height' => 300,
            'crop' => 'fit',
            'quality' => 'auto',
            'format' => 'webp',
        ]);

        $this->assertStringContainsString('sample_image', $url);
        $this->assertStringContainsString('w_600', $url);
        $this->assertStringContainsString('h_300', $url);
        $this->assertStringContainsString('c_fit', $url);
    }

    public function test_url_method_returns_direct_or_transformed_url(): void
    {
        $direct = $this->service->url('sample_image');
        $this->assertStringContainsString('sample_image', $direct);

        $transformed = $this->service->url('sample_image', ['width' => 500]);
        $this->assertStringContainsString('w_500', $transformed);
    }

    public function test_image_tag_generates_valid_html_tag(): void
    {
        $tag = $this->service->imageTag('profile', ['width' => 200, 'height' => 200], [
            'alt' => 'User Profile',
            'class' => 'rounded-full border',
        ]);

        $this->assertStringStartsWith('<img', $tag);
        $this->assertStringContainsString('src="', $tag);
        $this->assertStringContainsString('profile', $tag);
        $this->assertStringContainsString('alt="User Profile"', $tag);
        $this->assertStringContainsString('class="rounded-full border"', $tag);
        $this->assertStringContainsString('loading="lazy"', $tag);
    }
}
