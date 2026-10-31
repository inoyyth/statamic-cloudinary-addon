# Changelog

All notable changes to `inoyyth/statamic-cloudinary` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2026-10-31

### Fixed
- **Root Metadata Path Consistency**: Fixed `getUrl()` trimming leading dots so `.meta/<filename>.yaml` files in the container root survive read/write round-trips without returning 404 or wiping custom metadata and alt text.
- **Raw File Extension Preservation**: Updated `getPublicId()` to retain full filename and extension for raw files (`.pdf`, `.yaml`, `.zip`, etc.), ensuring PDFs and `.meta` files are correctly stored and retrieved.
- **Container Listing Completeness**: Updated `listContents()` to query across all Cloudinary resource types (`image`, `video`, `raw`) with pagination and context support, preventing PDFs, videos, and custom `.meta` files from dropping out of the container when rebuilding caches.
- **JPEG Format Mapping**: Stored original relative path in Cloudinary `context` upon upload and retrieved it during `listContents()`, preventing `.jpeg` files from dropping out or mismatching when Cloudinary normalizes format to `jpg`.
- **Stale Metadata Cache Invalidation**: Added `'invalidate' => true` to upload, rename, and destroy operations, along with cache-revalidation headers during `read()` and `readStream()`, ensuring fresh metadata is always fetched without CDN cache lag.
- **TLS Peer Verification**: Removed `verify_peer => false` and `verify_peer_name => false` from `read()` and `readStream()`, enforcing secure TLS certificate verification when downloading assets.

### Added
- **Multi-Site Scoping & Folder Prefix**: Added optional `prefix` (or `folder`) configuration in disk settings and config (`CLOUDINARY_PREFIX`), allowing multiple sites or environments to share a single Cloudinary account cleanly.
- **Configurable Fallback Image**: Changed default fallback image from hardcoded `images/resume_banner_hero.jpg` to `null` (`STATAMIC_CLOUDINARY_FALLBACK`), returning empty string for blank image fields rather than broken paths.
- **Comprehensive Adapter Tests**: Added tests covering write -> list -> read round-trips for root images, `.jpeg` files, PDFs, MP4s, root `.meta` files, and prefix scoping.

## [1.0.0] - 2026-10-25

### Added
- Initial release of the Statamic Cloudinary Addon.
- Flysystem v3 storage adapter for Statamic Asset Containers.
- Antlers tags (`cloudinary:url`, `cloudinary:transform`, `cloudinary:image`).
- Blade directives (`@statamicImage`, `@cloudinary`, `@cloudinaryTransform`, `@cloudinaryImage`).
- Global helpers (`statamic_image_url`, `cloudinary_transformed_url`, `cloudinary_image_tag`, `cloudinary_upload`).
- Image resolver service with smart fallback support.

