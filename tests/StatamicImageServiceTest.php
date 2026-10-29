<?php

namespace Inoyyth\StatamicCloudinary\Tests;

use Inoyyth\StatamicCloudinary\Services\StatamicImageService;
use PHPUnit\Framework\TestCase;

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

    public function test_resolves_array_with_url(): void
    {
        $arrayData = ['url' => 'https://res.cloudinary.com/demo/image/upload/nested.jpg'];
        $this->assertEquals('https://res.cloudinary.com/demo/image/upload/nested.jpg', $this->service->resolveImageUrl($arrayData));
    }

    public function test_resolves_null_with_fallback(): void
    {
        $this->assertEquals('https://example.com/fallback.jpg', $this->service->resolveImageUrl(null, 'https://example.com/fallback.jpg'));
    }
}
