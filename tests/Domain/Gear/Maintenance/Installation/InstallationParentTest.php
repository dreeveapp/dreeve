<?php

namespace App\Tests\Domain\Gear\Maintenance\Installation;

use App\Domain\Gear\GearId;
use App\Domain\Gear\Maintenance\GearComponentId;
use App\Domain\Gear\Maintenance\Installation\InstallationParent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InstallationParentTest extends TestCase
{
    public function testFromStringForGear(): void
    {
        $parent = InstallationParent::fromString('gear:gear-b123');

        $this->assertEquals(GearId::fromUnprefixed('b123'), $parent->getGearId());
        $this->assertNull($parent->getGearComponentId());
        $this->assertSame('gear:gear-b123', (string) $parent);
    }

    public function testFromStringForComponent(): void
    {
        $parent = InstallationParent::fromString('component:gearComponent-wheelset');

        $this->assertEquals(GearComponentId::fromString('gearComponent-wheelset'), $parent->getGearComponentId());
        $this->assertNull($parent->getGearId());
        $this->assertSame('"component:gearComponent-wheelset"', json_encode($parent));
    }

    #[DataProvider('provideInvalidParents')]
    public function testFromStringThrowsOnAnInvalidParent(string $parent): void
    {
        $this->expectException(\InvalidArgumentException::class);

        InstallationParent::fromString($parent);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidParents(): iterable
    {
        yield 'no type' => ['b123'];
        yield 'unknown type' => ['bike:b123'];
        yield 'no id' => ['gear:'];
        yield 'component id without prefix' => ['component:wheelset'];
    }
}
