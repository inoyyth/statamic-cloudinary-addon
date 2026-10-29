<?php

namespace Inoyyth\StatamicCloudinary\Tests;

use Illuminate\Support\Collection;
use Inoyyth\StatamicCloudinary\Services\StatamicImageService;
use PHPUnit\Framework\TestCase;
use Statamic\Fields\Value;

class StatamicImageServiceTest extends TestCase
{
    protected StatamicImageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StatamicImageService;
    }

    public function test_resolves_direct_http_and_https_urls(): void
    {
        $url1 = 'https://res.cloudinary.com/demo/image/upload/sample.jpg';
        $url2 = 'http://example.com/test.png';
        $url3 = '//res.cloudinary.com/demo/image/upload/protocol.jpg';

        $this->assertEquals($url1, $this->service->resolveImageUrl($url1));
        $this->assertEquals($url2, $this->service->resolveImageUrl($url2));
        $this->assertEquals($url3, $this->service->resolveImageUrl($url3));
    }

    public function test_resolves_statamic_augmented_value_object(): void
    {
        $value = new Value('https://res.cloudinary.com/demo/image/upload/sample.jpg');
        $this->assertEquals('https://res.cloudinary.com/demo/image/upload/sample.jpg', $this->service->resolveImageUrl($value));

        $nestedValue = new Value(new Value('https://res.cloudinary.com/demo/image/upload/nested.jpg'));
        $this->assertEquals('https://res.cloudinary.com/demo/image/upload/nested.jpg', $this->service->resolveImageUrl($nestedValue));
    }

    public function test_resolves_collection_of_images(): void
    {
        $collection = collect([
            'https://res.cloudinary.com/demo/image/upload/first.jpg',
            'https://res.cloudinary.com/demo/image/upload/second.jpg',
        ]);
        $this->assertEquals('https://res.cloudinary.com/demo/image/upload/first.jpg', $this->service->resolveImageUrl($collection));

        $empty = new Collection([]);
        $this->assertEquals('https://example.com/fallback.jpg', $this->service->resolveImageUrl($empty, 'https://example.com/fallback.jpg'));
    }

    public function test_resolves_array_with_url(): void
    {
        $arrayData = ['url' => 'https://res.cloudinary.com/demo/image/upload/nested.jpg'];
        $this->assertEquals('https://res.cloudinary.com/demo/image/upload/nested.jpg', $this->service->resolveImageUrl($arrayData));

        $listData = ['https://res.cloudinary.com/demo/image/upload/item.jpg'];
        $this->assertEquals('https://res.cloudinary.com/demo/image/upload/item.jpg', $this->service->resolveImageUrl($listData));
    }

    public function test_resolves_null_or_empty_with_fallback(): void
    {
        $this->assertEquals('https://example.com/fallback.jpg', $this->service->resolveImageUrl(null, 'https://example.com/fallback.jpg'));
        $this->assertEquals('https://example.com/fallback.jpg', $this->service->resolveImageUrl('', 'https://example.com/fallback.jpg'));
    }

    public function test_aliases_work_identically(): void
    {
        $url = 'https://res.cloudinary.com/demo/image/upload/test.jpg';
        $this->assertEquals($url, $this->service->resolve($url));
        $this->assertEquals($url, $this->service->url($url));
    }
}
