<?php

namespace Inoyyth\StatamicCloudinary;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Filesystem\FilesystemAdapter as LaravelFilesystemAdapter;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Inoyyth\StatamicCloudinary\Filesystems\CloudinaryFlysystemAdapter;
use Inoyyth\StatamicCloudinary\Modifiers\CloudinaryModifier;
use Inoyyth\StatamicCloudinary\Services\CloudinaryService;
use Inoyyth\StatamicCloudinary\Services\StatamicImageService;
use Inoyyth\StatamicCloudinary\Tags\CloudinaryTag;
use League\Flysystem\Filesystem;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $tags = [
        CloudinaryTag::class,
    ];

    protected $modifiers = [
        CloudinaryModifier::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/statamic-cloudinary.php',
            'statamic-cloudinary'
        );

        $this->app->singleton(CloudinaryService::class, function () {
            return new CloudinaryService;
        });

        $this->app->alias(CloudinaryService::class, 'cloudinary-service');
        $this->app->alias(CloudinaryService::class, 'Cloudinary');

        $this->app->singleton(StatamicImageService::class, function () {
            return new StatamicImageService;
        });

        $this->app->alias(StatamicImageService::class, 'statamic-image');
        $this->app->alias(StatamicImageService::class, 'statamic-image-service');
        $this->app->alias(StatamicImageService::class, 'StatamicImage');

        if (! function_exists('statamic_image_url')) {
            require_once __DIR__.'/Support/Helpers/helpers.php';
        }
    }

    /**
     * Boot addon services.
     */
    public function bootAddon(): void
    {
        $this->registerPublishing();
        $this->bootCloudinaryConfiguration();
        $this->bootStorageDriver();
        $this->bootBladeDirectives();
    }

    /**
     * Register config publishing.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/statamic-cloudinary.php' => config_path('statamic-cloudinary.php'),
            ], 'statamic-cloudinary-config');
        }
    }

    /**
     * Initialize Cloudinary Configuration if URL is provided.
     */
    protected function bootCloudinaryConfiguration(): void
    {
        $cloudinaryUrl = config('statamic-cloudinary.url') ?? env('CLOUDINARY_URL');

        if ($cloudinaryUrl) {
            Configuration::instance($cloudinaryUrl);
        }
    }

    /**
     * Register the 'cloudinary' Flysystem storage driver for Laravel and Statamic.
     */
    protected function bootStorageDriver(): void
    {
        Storage::extend('cloudinary', function ($app, $config) {
            $cloudName = $config['cloud_name'] ?? config('statamic-cloudinary.cloud_name') ?? env('CLOUDINARY_CLOUD_NAME');
            $apiKey = $config['api_key'] ?? config('statamic-cloudinary.api_key') ?? env('CLOUDINARY_API_KEY');
            $apiSecret = $config['api_secret'] ?? config('statamic-cloudinary.api_secret') ?? env('CLOUDINARY_API_SECRET');
            $secure = $config['secure'] ?? config('statamic-cloudinary.secure') ?? env('CLOUDINARY_SECURE', true);

            $cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => $cloudName,
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                ],
                'url' => [
                    'secure' => $secure,
                ],
            ]);

            $adapter = new CloudinaryFlysystemAdapter($cloudinary);
            $flysystem = new Filesystem($adapter, $config);

            return new LaravelFilesystemAdapter($flysystem, $adapter, $config);
        });
    }

    /**
     * Register Blade directives for template ease.
     */
    protected function bootBladeDirectives(): void
    {
        Blade::directive('statamicImage', function ($expression) {
            return "<?php echo app(\\Inoyyth\\StatamicCloudinary\\Services\\StatamicImageService::class)->resolveImageUrl({$expression}); ?>";
        });

        Blade::directive('resolveImageUrl', function ($expression) {
            return "<?php echo app(\\Inoyyth\\StatamicCloudinary\\Services\\StatamicImageService::class)->resolveImageUrl({$expression}); ?>";
        });

        Blade::directive('cloudinary', function ($expression) {
            return "<?php echo app(\\Inoyyth\\StatamicCloudinary\\Services\\StatamicImageService::class)->resolveImageUrl({$expression}); ?>";
        });
    }
}
