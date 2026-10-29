# Statamic Cloudinary Addon

Seamless Cloudinary integration for **Statamic CMS**, featuring a complete Flysystem v3 storage adapter for Statamic Asset Containers, direct upload services, Antlers tags, Blade directives, and smart image resolution utilities.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/inoyyth/statamic-cloudinary.svg?style=flat-square)](https://packagist.org/packages/inoyyth/statamic-cloudinary)
[![Run Tests](https://github.com/Inoyyth/statamic-cloudinary/actions/workflows/run-tests.yml/badge.svg)](https://github.com/Inoyyth/statamic-cloudinary/actions/workflows/run-tests.yml)
[![License](https://img.shields.io/github/license/Inoyyth/statamic-cloudinary.svg?style=flat-square)](LICENSE)

---

## Features

- **Statamic Asset Container Integration**: Use Cloudinary as your Statamic Asset Container disk driver with full CP file listing, cursor-paginated folder browsing, uploading, renaming, and deleting.
- **Flysystem v3 Adapter**: High-performance, cached `CloudinaryFlysystemAdapter` adhering to League Flysystem standards.
- **Smart Image Resolver**: Resolves URLs from Statamic `Value` objects, `Asset` instances, collections, nested arrays, and Cloudinary paths with intelligent fallback handling.
- **Antlers Tags & Modifiers**: Effortlessly render responsive `<img>` tags and on-the-fly transformations inside Antlers templates.
- **Blade Directives & Helpers**: Use `@statamicImage()`, `@cloudinary()`, `@cloudinaryTransform()`, `@cloudinaryImage()`, or global helpers (`statamic_image_url()`, `cloudinary_transformed_url()`, `cloudinary_image_tag()`).
- **Direct Cloudinary Operations**: Direct upload, deletion, public ID extraction, and transformed URL generation via `Cloudinary` facade and `CloudinaryService`.

---

## Requirements

- PHP `^8.2`, `^8.3`, `^8.4`, or `^8.5`
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

You can now configure a Statamic Asset Container to use the `cloudinary` disk!
Inside the Statamic Control Panel:
1. Navigate to **Assets** -> **Configure Containers**.
2. Select or create your Container (e.g. `cloudinary`).
3. Set **Disk** to `cloudinary`.

All assets uploaded through Statamic CP or blueprint fields configured with `container: cloudinary` will now be stored in and served from Cloudinary!

---

## Usage

### In Antlers Templates

#### 1. Resolving URLs
```antlers
{{-- Resolving asset URL via tag --}}
<img src="{{ cloudinary:url src=banner_image }}" alt="Banner" />

{{-- Using modifiers --}}
<img src="{{ banner_image | cloudinary }}" alt="Banner" />
<img src="{{ banner_image | statamic_image }}" alt="Banner" />
```

#### 2. On-the-fly Image Transformations
```antlers
{{-- Transforming with dimensions, crop, quality & format --}}
<img src="{{ cloudinary:transform src=banner_image width=800 height=400 crop='fill' quality='auto' format='webp' }}" alt="Transformed Banner" />

{{-- Using modifier pipeline --}}
<img src="{{ banner_image | cloudinary:transform(800, 400, 'fill', 'auto', 'webp') }}" alt="Transformed Banner" />
```

#### 3. Generating Responsive HTML `<img>` Tags
```antlers
{{-- Renders a complete <img ... /> with transformations and HTML attributes --}}
{{ cloudinary:image src=hero_image width=1200 height=600 crop='fill' alt="Hero Banner" class="w-full rounded-2xl shadow-xl" loading="lazy" }}
```

---

### In Blade Templates

```blade
{{-- Direct URL Resolution --}}
<img src="@statamicImage($page->banner_image)" alt="Banner" />
<img src="@cloudinary($page->hero_image, 'images/default-fallback.jpg')" alt="Hero" />

{{-- Transformed URL --}}
<img src="@cloudinaryTransform($page->avatar, ['width' => 200, 'height' => 200, 'crop' => 'fill', 'gravity' => 'face'])" alt="Avatar" />

{{-- Complete HTML Image Tag --}}
{!! \Inoyyth\StatamicCloudinary\Facades\Cloudinary::imageTag($page->banner_image, ['width' => 800, 'height' => 400], ['alt' => 'Banner', 'class' => 'w-full']) !!}
```

---

### Global Helper Functions

```php
// Resolve any Statamic asset, string, or collection
$url = statamic_image_url($entry->banner_image);

// Generate transformed URL
$transformed = cloudinary_transformed_url($entry->avatar, 400, 400);
$custom = cloudinary_transformed_url($entry->avatar, ['width' => 800, 'crop' => 'fit', 'format' => 'webp']);

// Generate HTML <img> tag
$tag = cloudinary_image_tag($entry->avatar, ['width' => 120, 'height' => 120], ['class' => 'rounded-full']);

// Direct upload
$result = cloudinary_upload($file->getRealPath(), 'uploads/resumes');
```

---

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
$transformedUrl = Cloudinary::getTransformedUrl($publicId, ['width' => 600, 'height' => 600, 'crop' => 'fill']);

// Delete an asset
Cloudinary::delete($publicId);
```

---

## Running Tests

The test suite covers Flysystem adapter operations, Antlers tags & modifiers, Blade directives, service providers, and helper functions:

```bash
composer test
# or
./vendor/bin/phpunit
```

---

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
