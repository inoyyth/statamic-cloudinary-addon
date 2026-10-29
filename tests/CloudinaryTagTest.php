<?php

namespace Inoyyth\StatamicCloudinary\Tests;

use Cloudinary\Cloudinary;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Inoyyth\StatamicCloudinary\Services\CloudinaryService;
use Inoyyth\StatamicCloudinary\Services\StatamicImageService;
use Inoyyth\StatamicCloudinary\Tags\CloudinaryTag;
use PHPUnit\Framework\TestCase;

class CloudinaryTagTest extends TestCase
{
    protected CloudinaryTag $tag;

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

        $this->tag = new CloudinaryTag;
        $this->tag->setContext([]);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    public function test_tag_url_resolves_image_path(): void
    {
        $this->tag->setParameters([
            'src' => 'https://res.cloudinary.com/demo/image/upload/sample.jpg',
        ]);

        $this->assertEquals('https://res.cloudinary.com/demo/image/upload/sample.jpg', $this->tag->url());
        $this->assertEquals('https://res.cloudinary.com/demo/image/upload/sample.jpg', $this->tag->index());
    }

    public function test_tag_transform_generates_transformed_url(): void
    {
        $this->tag->setParameters([
            'src' => 'sample_banner',
            'width' => 800,
            'height' => 400,
            'crop' => 'fill',
        ]);

        $url = $this->tag->transform();
        $this->assertStringContainsString('sample_banner', $url);
        $this->assertStringContainsString('w_800', $url);
        $this->assertStringContainsString('h_400', $url);
    }

    public function test_tag_image_outputs_html_img_element(): void
    {
        $this->tag->setParameters([
            'src' => 'hero_img',
            'width' => 600,
            'height' => 300,
            'alt' => 'Hero Banner',
            'class' => 'w-full shadow-lg',
        ]);

        $html = $this->tag->image();
        $this->assertStringStartsWith('<img', $html);
        $this->assertStringContainsString('hero_img', $html);
        $this->assertStringContainsString('alt="Hero Banner"', $html);
        $this->assertStringContainsString('class="w-full shadow-lg"', $html);

        $this->assertEquals($html, $this->tag->tag());
    }
}
