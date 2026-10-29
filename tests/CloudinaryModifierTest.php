<?php

namespace Inoyyth\StatamicCloudinary\Tests;

use Cloudinary\Cloudinary;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Inoyyth\StatamicCloudinary\Modifiers\CloudinaryModifier;
use Inoyyth\StatamicCloudinary\Services\CloudinaryService;
use Inoyyth\StatamicCloudinary\Services\StatamicImageService;
use PHPUnit\Framework\TestCase;

class CloudinaryModifierTest extends TestCase
{
    protected CloudinaryModifier $modifier;

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

        $this->modifier = new CloudinaryModifier;
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    public function test_modifier_resolves_image_url(): void
    {
        $url = 'https://res.cloudinary.com/demo/image/upload/sample.jpg';
        $this->assertEquals($url, $this->modifier->index($url));
    }

    public function test_modifier_transforms_image_with_parameters(): void
    {
        $result = $this->modifier->transform('sample_pic', [800, 600, 'fill', 'auto', 'webp']);

        $this->assertStringContainsString('sample_pic', $result);
        $this->assertStringContainsString('w_800', $result);
        $this->assertStringContainsString('h_600', $result);
        $this->assertStringContainsString('c_fill', $result);
    }
}
