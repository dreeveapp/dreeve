<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Stream;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Stream\ActivitiesExcludedFromPeakPowerOutputs;
use PHPUnit\Framework\TestCase;

class ActivitiesExcludedFromPeakPowerOutputsTest extends TestCase
{
    public function testFromWithEmptyArray(): void
    {
        $excluded = ActivitiesExcludedFromPeakPowerOutputs::from([]);

        $this->assertTrue($excluded->isEmpty());
        $this->assertCount(0, $excluded);
    }

    public function testFromWithActivityIds(): void
    {
        $excluded = ActivitiesExcludedFromPeakPowerOutputs::from(['123', '456']);

        $this->assertCount(2, $excluded);
        $this->assertFalse($excluded->isEmpty());
        $this->assertTrue($excluded->has(ActivityId::fromUnprefixed('123')));
        $this->assertTrue($excluded->has(ActivityId::fromUnprefixed('456')));
        $this->assertFalse($excluded->has(ActivityId::fromUnprefixed('789')));
    }

    public function testFromWithSingleActivityId(): void
    {
        $excluded = ActivitiesExcludedFromPeakPowerOutputs::from(['42']);

        $this->assertCount(1, $excluded);
        $this->assertTrue($excluded->has(ActivityId::fromUnprefixed('42')));
    }
}
