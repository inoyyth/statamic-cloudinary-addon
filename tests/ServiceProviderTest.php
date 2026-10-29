<?php

namespace Inoyyth\StatamicCloudinary\Tests;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Inoyyth\StatamicCloudinary\Services\CloudinaryService;
use Inoyyth\StatamicCloudinary\Services\StatamicImageService;
use Inoyyth\StatamicCloudinary\ServiceProvider;
use PHPUnit\Framework\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_service_provider_registers_services_and_aliases(): void
    {
        $app = new Container;
        $app->instance('config', new ConfigRepository([
            'statamic-cloudinary' => [
                'cloud_name' => 'test_cloud',
                'api_key' => '123',
                'api_secret' => 'sec',
            ],
        ]));

        Container::setInstance($app);
        Facade::setFacadeApplication($app);

        $provider = new ServiceProvider($app);
        $provider->register();

        $this->assertTrue($app->bound(CloudinaryService::class));
        $this->assertTrue($app->bound('cloudinary-service'));
        $this->assertTrue($app->bound('Cloudinary'));

        $this->assertTrue($app->bound(StatamicImageService::class));
        $this->assertTrue($app->bound('statamic-image'));
        $this->assertTrue($app->bound('statamic-image-service'));
        $this->assertTrue($app->bound('StatamicImage'));

        $this->assertInstanceOf(CloudinaryService::class, $app->make('cloudinary-service'));
        $this->assertInstanceOf(StatamicImageService::class, $app->make('statamic-image'));

        Container::setInstance(null);
        Facade::setFacadeApplication(null);
    }
}
