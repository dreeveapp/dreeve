<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivitySummary;
use App\Domain\Activity\ActivitySummaryRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\DbalActivityRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Tests\ContainerTestCase;

class DbalActivitySummaryRepositoryTest extends ContainerTestCase
{
    private ActivitySummaryRepository $activitySummaryRepository;
    private ActivityRepository $activityRepository;

    public function testItShouldSaveAndFind(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $this->activityRepository->add(ActivityWithRawData::fromState(
            $activity,
            ['raw' => 'data']
        ));

        $persisted = $this->activitySummaryRepository->find($activity->getId());
        $this->assertEquals(
            ActivitySummary::create(
                name: $activity->getName(),
                startDateTime: $activity->getStartDate(),
                sportType: $activity->getSportType(),
            ),
            $persisted,
        );
    }

    public function testFindItShouldThrowWhenNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('Activity "activity-1" not found'));
        $this->activitySummaryRepository->find(ActivityId::fromUnprefixed(1));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activitySummaryRepository = $this->getContainer()->get(ActivitySummaryRepository::class);
        $this->activityRepository = new DbalActivityRepository(
            $this->getConnection(),
        );
    }
}
