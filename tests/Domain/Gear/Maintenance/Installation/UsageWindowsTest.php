<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance\Installation;

use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearId;
use App\Domain\Gear\Maintenance\Installation\UsageWindow;
use App\Domain\Gear\Maintenance\Installation\UsageWindows;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\TestCase;

class UsageWindowsTest extends TestCase
{
    public function testGetEarliestFrom(): void
    {
        $usageWindows = UsageWindows::fromArray([
            UsageWindow::create(
                gearId: GearId::fromUnprefixed('test'),
                from: SerializableDateTime::fromString('2025-03-01'),
                until: null,
                sportTypes: SportTypes::empty(),
            ),
            UsageWindow::create(
                gearId: GearId::fromUnprefixed('test'),
                from: null,
                until: null,
                sportTypes: SportTypes::empty(),
            ),
        ]);

        $this->assertNull($usageWindows->getEarliestFrom());
    }
}
