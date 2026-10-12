<?php

namespace App\Tests\Domain\Gear\Maintenance;

use App\Domain\Gear\Maintenance\GearMaintenanceConfig;
use PHPUnit\Framework\TestCase;

class GearMaintenanceConfigTest extends TestCase
{
    public function testFromArray(): void
    {
        $config = GearMaintenanceConfig::fromArray(['enabled' => true, 'ignoreRetiredGear' => true]);

        $this->assertTrue($config->isFeatureEnabled());
        $this->assertTrue($config->ignoreRetiredGear());
    }

    public function testFromArrayWhenEmpty(): void
    {
        $config = GearMaintenanceConfig::fromArray(null);

        $this->assertFalse($config->isFeatureEnabled());
        $this->assertFalse($config->ignoreRetiredGear());
    }
}
