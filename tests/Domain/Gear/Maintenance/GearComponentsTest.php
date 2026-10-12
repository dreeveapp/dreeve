<?php

namespace App\Tests\Domain\Gear\Maintenance;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\GearComponent;
use App\Domain\Gear\Maintenance\GearComponentId;
use App\Domain\Gear\Maintenance\GearComponents;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallation;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallations;
use App\Domain\Gear\Maintenance\Installation\UsageWindow;
use App\Domain\Gear\Maintenance\Installation\UsageWindows;
use App\Infrastructure\ValueObject\String\Name;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\TestCase;

class GearComponentsTest extends TestCase
{
    public function testResolveUsageWindowsFor(): void
    {
        $gearComponent = GearComponent::create(
            id: GearComponentId::fromUnprefixed('wheelset'),
            label: Name::fromString('Wheelset'),
            installations: GearComponentInstallations::fromArray([
                GearComponentInstallation::create(
                    gearId: GearId::fromUnprefixed('b1'),
                    from: null,
                    until: SerializableDateTime::fromString('2025-03-15'),
                ),
                GearComponentInstallation::create(
                    gearId: GearId::fromUnprefixed('b2'),
                    from: SerializableDateTime::fromString('2025-03-15'),
                    until: null,
                ),
            ]),
            localImagePath: null,
            purchasePrice: null,
        );

        $this->assertEquals(
            UsageWindows::fromArray([
                UsageWindow::create(
                    gearId: GearId::fromUnprefixed('b1'),
                    from: null,
                    until: SerializableDateTime::fromString('2025-03-15'),
                ),
                UsageWindow::create(
                    gearId: GearId::fromUnprefixed('b2'),
                    from: SerializableDateTime::fromString('2025-03-15'),
                    until: null,
                ),
            ]),
            GearComponents::fromArray([$gearComponent])->resolveUsageWindowsFor($gearComponent),
        );
    }

    public function testGetGearIdsCurrentlyUsing(): void
    {
        $gearComponent = GearComponent::create(
            id: GearComponentId::fromUnprefixed('wheelset'),
            label: Name::fromString('Wheelset'),
            installations: GearComponentInstallations::fromArray([
                GearComponentInstallation::create(
                    gearId: GearId::fromUnprefixed('b1'),
                    from: null,
                    until: SerializableDateTime::fromString('2025-03-15'),
                ),
                GearComponentInstallation::create(
                    gearId: GearId::fromUnprefixed('b2'),
                    from: SerializableDateTime::fromString('2025-03-15'),
                    until: null,
                ),
                GearComponentInstallation::create(
                    gearId: GearId::fromUnprefixed('b3'),
                    from: SerializableDateTime::fromString('2025-07-01'),
                    until: null,
                ),
                GearComponentInstallation::create(
                    gearId: GearId::fromUnprefixed('b2'),
                    from: null,
                    until: null,
                ),
            ]),
            localImagePath: null,
            purchasePrice: null,
        );

        $this->assertEquals(
            GearIds::fromArray([GearId::fromUnprefixed('b2')]),
            GearComponents::fromArray([$gearComponent])->getGearIdsCurrentlyUsing(
                $gearComponent,
                SerializableDateTime::fromString('2025-06-01 10:00:00'),
            ),
        );
    }
}
