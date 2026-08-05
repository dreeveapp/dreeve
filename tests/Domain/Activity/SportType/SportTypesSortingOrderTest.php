<?php

namespace App\Tests\Domain\Activity\SportType;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypesSortingOrder;
use PHPUnit\Framework\TestCase;

class SportTypesSortingOrderTest extends TestCase
{
    public function testFrom(): void
    {
        $this->assertEquals(
            SportTypesSortingOrder::fromArray(SportType::cases()),
            SportTypesSortingOrder::from([])
        );

        $allSportTypesWithoutSortedOnes = [];
        foreach (SportType::cases() as $sportType) {
            if (in_array($sportType, [
                SportType::POOL_SWIM,
                SportType::RUN,
            ])) {
                continue;
            }

            $allSportTypesWithoutSortedOnes[] = $sportType;
        }

        $this->assertEquals(
            SportTypesSortingOrder::fromArray([SportType::POOL_SWIM, SportType::RUN, ...$allSportTypesWithoutSortedOnes]),
            SportTypesSortingOrder::from(['Swim', 'Run'])
        );
    }
}
