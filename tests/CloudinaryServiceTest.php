<?php

namespace Inoyyth\StatamicCloudinary\Tests;

use Inoyyth\StatamicCloudinary\Services\CloudinaryService;
use PHPUnit\Framework\TestCase;

class CloudinaryServiceTest extends TestCase
{
    public function test_service_can_instantiate(): void
    {
        $service = new CloudinaryService;
        $this->assertInstanceOf(CloudinaryService::class, $service);
    }
}
