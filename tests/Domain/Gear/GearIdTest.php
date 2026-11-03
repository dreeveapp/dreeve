<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear;

use App\Domain\Gear\GearId;
use PHPUnit\Framework\TestCase;

class GearIdTest extends TestCase
{
    public function testIsPrefixedWithStravaPrefix(): void
    {
        $this->assertTrue(
            GearId::fromUnprefixed('b1234')->isPrefixedWithStravaPrefix()
        );
        $this->assertTrue(
            GearId::fromUnprefixed('g1234')->isPrefixedWithStravaPrefix()
        );
        $this->assertFalse(
            GearId::fromUnprefixed('1234')->isPrefixedWithStravaPrefix()
        );
    }

    public function testMatches(): void
    {
        $this->assertTrue(
            GearId::fromUnprefixed('b1234')->matches(GearId::fromUnprefixed('b1234'))
        );
        $this->assertTrue(
            GearId::fromUnprefixed('1234')->matches(GearId::fromUnprefixed('b1234'))
        );
        $this->assertTrue(
            GearId::fromUnprefixed('b1234')->matches(GearId::fromUnprefixed('1234'))
        );
        $this->assertFalse(
            GearId::fromUnprefixed('b2234')->matches(GearId::fromUnprefixed('1234'))
        );
    }
}
