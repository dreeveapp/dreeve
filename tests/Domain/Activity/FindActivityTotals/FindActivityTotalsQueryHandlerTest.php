<?php

namespace App\Tests\Domain\Activity\FindActivityTotals;

use App\Domain\Activity\Activities;
use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\FindActivityTotals\FindActivityTotals;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;

class FindActivityTotalsQueryHandlerTest extends ContainerTestCase
{
    use ProvideTestData;

    private QueryBus $queryBus;

    public function testHandleMatchesSummingEveryActivityInPhp(): void
    {
        $this->provideFullTestSet();

        /** @var Activities $activities */
        $activities = $this->getContainer()->get(ActivityRepository::class)->findAll();
        $response = $this->queryBus->ask(new FindActivityTotals());

        $this->assertEquals(count($activities), $response->getTotalActivities());
        $this->assertEqualsWithDelta(
            $activities->sum(fn (Activity $activity): float => $activity->getDistance()->toFloat()),
            $response->getTotalDistance()->toFloat(),
            0.001,
        );
        $this->assertEqualsWithDelta(
            $activities->sum(fn (Activity $activity): float => $activity->getElevation()->toFloat()),
            $response->getTotalElevation()->toFloat(),
            0.001,
        );
        $this->assertEquals(
            (int) $activities->sum(fn (Activity $activity): ?int => $activity->getCalories()),
            $response->getTotalCalories(),
        );
        $this->assertEquals(
            (int) $activities->sum(fn (Activity $activity): int => $activity->getMovingTimeInSeconds()),
            $response->getTotalMovingTimeInSeconds(),
        );
        $this->assertEquals(
            count(array_unique($activities->map(fn (Activity $activity): string => $activity->getStartDate()->format('Ymd')))),
            $response->getTotalDaysOfWorkingOut(),
        );
        $this->assertEquals(
            $activities->getFirstActivityStartDate(),
            $response->getFirstActivityStartDate(),
        );
    }

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
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->queryBus = $this->getContainer()->get(QueryBus::class);
    }
}
