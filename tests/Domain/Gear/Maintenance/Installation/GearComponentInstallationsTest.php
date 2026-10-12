<?php

namespace App\Tests\Domain\Gear\Maintenance\Installation;

use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallation;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallations;
use App\Domain\Gear\Maintenance\Installation\InstallationParent;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\TestCase;

class GearComponentInstallationsTest extends TestCase
{
    public function testGetGearIds(): void
    {
        $installations = GearComponentInstallations::fromArray([
            GearComponentInstallation::create(
                parent: InstallationParent::gear(GearId::fromUnprefixed('1')),
                from: null,
                until: SerializableDateTime::fromString('2025-03-01'),
                sportTypes: SportTypes::empty(),
            ),
            GearComponentInstallation::create(
                parent: InstallationParent::gear(GearId::fromUnprefixed('2')),
                from: null,
                until: null,
                sportTypes: SportTypes::empty(),
            ),
            GearComponentInstallation::create(
                parent: InstallationParent::gear(GearId::fromUnprefixed('1')),
                from: SerializableDateTime::fromString('2025-11-01'),
                until: null,
                sportTypes: SportTypes::empty(),
            ),
        ]);

        $this->assertEquals(
            GearIds::fromArray([GearId::fromUnprefixed('1'), GearId::fromUnprefixed('2')]),
            $installations->getGearIds(),
        );
    }
}
