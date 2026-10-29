<?php

namespace Inoyyth\StatamicCloudinary\Tests;

use Cloudinary\Cloudinary;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Inoyyth\StatamicCloudinary\Services\CloudinaryService;
use Inoyyth\StatamicCloudinary\Services\StatamicImageService;
use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $app = new Container;
        $cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => 'demo_cloud',
                'api_key' => '1234567890',
                'api_secret' => 'abcdefghij',
            ],
            'url' => ['secure' => true],
        ]);

        $app->singleton(CloudinaryService::class, fn () => new CloudinaryService($cloudinary));
        $app->singleton(StatamicImageService::class, fn () => new StatamicImageService);
        Container::setInstance($app);
        Facade::setFacadeApplication($app);

        require_once __DIR__ . '/../src/Support/Helpers/helpers.php';
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    public function test_statamic_image_url_helper(): void
    {
        $url = 'https://res.cloudinary.com/demo/image/upload/sample.jpg';
        $this->assertEquals($url, statamic_image_url($url));
    }

    public function test_cloudinary_transformed_url_helper(): void
    {
        $url = cloudinary_transformed_url('sample_logo', 300, 150);
        $this->assertStringContainsString('sample_logo', $url);
        $this->assertStringContainsString('w_300', $url);
        $this->assertStringContainsString('h_150', $url);
    }

    public function test_cloudinary_url_helper(): void
    {
        $url = cloudinary_url('sample_logo');
        $this->assertStringContainsString('sample_logo', $url);
    }

    public function test_cloudinary_image_tag_helper(): void
    {
        $tag = cloudinary_image_tag('avatar', ['width' => 100, 'height' => 100], ['alt' => 'Avatar']);
        $this->assertStringStartsWith('<img', $tag);
        $this->assertStringContainsString('avatar', $tag);
        $this->assertStringContainsString('alt="Avatar"', $tag);
    }
}
