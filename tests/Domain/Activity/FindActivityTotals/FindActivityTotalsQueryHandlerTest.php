<?php

namespace App\Tests\Domain\Activity\FindActivityTotals;

use App\Domain\Activity\FindActivityTotals\FindActivityTotals;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Tests\ContainerTestCase;

class FindActivityTotalsQueryHandlerTest extends ContainerTestCase
{
    private QueryBus $queryBus;

    public function testHandleWithoutActivities(): void
    {
        $response = $this->queryBus->ask(new FindActivityTotals());

        $this->assertEquals(0, $response->getTotalActivities());
        $this->assertEquals(Kilometer::zero(), $response->getTotalDistance());
        $this->assertEquals(Meter::zero(), $response->getTotalElevation());
        $this->assertEquals(0, $response->getTotalCalories());
        $this->assertEquals(0, $response->getTotalMovingTimeInSeconds());
        $this->assertEquals(0, $response->getTotalDaysOfWorkingOut());
        $this->assertNull($response->getFirstActivityStartDate());
        $this->assertNull($response->getLastActivityStartDate());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->queryBus = $this->getContainer()->get(QueryBus::class);
    }
}
