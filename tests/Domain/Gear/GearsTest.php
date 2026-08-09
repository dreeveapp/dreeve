<?php

namespace App\Tests\Domain\Gear;

use App\Domain\Gear\GearId;
use App\Domain\Gear\Gears;
use PHPUnit\Framework\TestCase;

class GearsTest extends TestCase
{
    public function testGetByGearId(): void
    {
        $gear = GearBuilder::fromDefaults()->withGearId(GearId::fromUnprefixed('1'))->build();
        $otherGear = GearBuilder::fromDefaults()->withGearId(GearId::fromUnprefixed('2'))->build();

        $this->assertEquals(
            $gear,
            Gears::fromArray([$gear, $otherGear])->getByGearId(GearId::fromUnprefixed('1'))
        );
    }

    public function testGetByGearIdWhenNotFound(): void
    {
        $this->assertNull(
            Gears::fromArray([GearBuilder::fromDefaults()->withGearId(GearId::fromUnprefixed('1'))->build()])
                ->getByGearId(GearId::fromUnprefixed('2'))
        );
    }

    public function testGetByGearIdWithoutAGearId(): void
    {
        $this->assertNull(
            Gears::fromArray([GearBuilder::fromDefaults()->build()])->getByGearId(null)
        );
    }
}
