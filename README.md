# Statamic Cloudinary Addon

Seamless Cloudinary integration for **Statamic CMS**, featuring a complete Flysystem v3 storage adapter for Statamic Asset Containers, direct upload services, Antlers tags, Blade directives, and smart image resolution utilities.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/inoyyth/statamic-cloudinary.svg?style=flat-square)](https://packagist.org/packages/inoyyth/statamic-cloudinary)
[![License](https://img.shields.io/github/license/Inoyyth/statamic-cloudinary.svg?style=flat-square)](LICENSE)

---

## Features

- **Statamic Asset Container Integration**: Use Cloudinary as your Statamic Asset Container disk driver with full CP file listing, folder browsing, uploading, renaming, and deleting.
- **Flysystem v3 Adapter**: High-performance, cached `CloudinaryFlysystemAdapter` adhering to League Flysystem standards.
- **Smart Image Resolver**: Resolves URLs from Statamic `Value` objects, `Asset` instances, collections, nested arrays, and Cloudinary paths with intelligent fallback handling.
- **Antlers Tags & Modifiers**: Effortlessly render and transform Cloudinary assets inside Antlers templates.
- **Blade Directives & Helpers**: Use `@statamicImage()`, `@resolveImageUrl()`, or `statamic_image_url()` across your Blade templates.
- **Direct Cloudinary Operations**: Direct upload, deletion, and transformed URL generation via `Cloudinary` facade and `CloudinaryService`.

---

## Requirements

- PHP `^8.2`, `^8.3`, or `^8.4`
- Statamic CMS `^5.0` or `^6.0`
- Laravel `^10.0`, `^11.0`, `^12.0`, or `^13.0`
- Cloudinary Account

---

## Installation

Install the package via Composer:

```bash
composer require inoyyth/statamic-cloudinary
```

The package will automatically register its ServiceProvider through Laravel/Statamic package discovery.

### Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=statamic-cloudinary-config
```

This publishes `config/statamic-cloudinary.php`.

---

## Configuration

### 1. Environment Variables

Add your Cloudinary credentials to your `.env` file:

```env
CLOUDINARY_CLOUD_NAME=your_cloud_name
CLOUDINARY_API_KEY=your_api_key
CLOUDINARY_API_SECRET=your_api_secret

# Or use the single Cloudinary connection URL:
CLOUDINARY_URL=cloudinary://your_api_key:your_api_secret@your_cloud_name
```

### 2. Configure Filesystem Disk

In `config/filesystems.php`, add the `cloudinary` disk:

```php
'disks' => [
    // ...
    'cloudinary' => [
        'driver' => 'cloudinary',
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'url' => env('CLOUDINARY_URL'),
        'secure' => env('CLOUDINARY_SECURE', true),
    ],
],
```

### 3. Statamic Asset Container

You can now create or configure a Statamic Asset Container to use the `cloudinary` disk!
Inside the Statamic Control Panel:
1. Navigate to **Assets** -> **Configure Containers**.
2. Select or create your Container (e.g. `cloudinary`).
3. Set **Disk** to `cloudinary`.

All assets uploaded through Statamic CP or blueprint fields configured with `container: cloudinary` will now be stored in and served from Cloudinary!

---

## Usage

### In Blade Templates

```blade
{{-- Using Blade Directives --}}
<img src="@statamicImage($page->banner_image)" alt="Banner" />
<img src="@resolveImageUrl($page->hero_image, 'images/default-fallback.jpg')" alt="Hero" />

{{-- Using Helper Function --}}
<img src="{{ statamic_image_url($page->avatar) }}" alt="Avatar" />
```

### In Antlers Templates

```antlers
{{-- Resolving asset URL --}}
<img src="{{ cloudinary:url src=banner_image }}" alt="Banner" />

{{-- Using modifier --}}
<img src="{{ banner_image | cloudinary }}" alt="Banner" />
<img src="{{ banner_image | statamic_image }}" alt="Banner" />

{{-- Transforming image with width and height --}}
<img src="{{ cloudinary:transform src=banner_image width=800 height=400 }}" alt="Transformed Banner" />
```

### In PHP / Livewire / Controllers

```php
use Inoyyth\StatamicCloudinary\Facades\StatamicImage;
use Inoyyth\StatamicCloudinary\Facades\Cloudinary;

// Resolve any Statamic asset, string, or collection
$imageUrl = StatamicImage::resolve($entry->image);

// Direct Cloudinary Upload
$result = Cloudinary::upload($request->file('avatar')->getRealPath(), 'avatars');
$publicId = $result['public_id'];

// Get transformed URL
$transformedUrl = Cloudinary::getTransformedUrl($publicId, 600, 600);

// Delete an asset
Cloudinary::delete($publicId);
```

---

## Running Tests

```bash
composer test
# or
./vendor/bin/phpunit
```

---

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
