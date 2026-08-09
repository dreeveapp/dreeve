<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\EnrichedActivityRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;

class DbalEnrichedActivityRepositoryTest extends ContainerTestCase
{
    use ProvideTestData;

    private EnrichedActivityRepository $enrichedActivityRepository;

    public function testFind(): void
    {
        $this->provideFullTestSet();

        $activityId = ActivityId::fromUnprefixed('9756441741');
        $enrichedActivity = $this->enrichedActivityRepository->find($activityId);

        $this->assertEquals($activityId, $enrichedActivity->getActivity()->getId());
        $this->assertNull($enrichedActivity->getNormalizedPower());
        $this->assertEquals(102, $enrichedActivity->getMaxCadence());
        $this->assertEquals('Retro Race Bike', $enrichedActivity->getGearName());
        $this->assertTrue($enrichedActivity->hasDetailedPowerData());
        $this->assertEquals(493, $enrichedActivity->getBestAveragePowerForTimeInterval(5)?->getPower());
        $this->assertNull($enrichedActivity->getBestAveragePowerForTimeInterval(1));
    }

    public function testFindHydratesTheSameActivityAsTheActivityRepository(): void
    {
        $this->provideFullTestSet();

        $activityId = ActivityId::fromUnprefixed('9756441741');

        $this->assertEquals(
            $this->getContainer()->get(ActivityRepository::class)->find($activityId),
            $this->enrichedActivityRepository->find($activityId)->getActivity(),
        );
    }

    public function testFindForAnActivityWithoutGearStreamsOrMetrics(): void
    {
        $activityId = ActivityId::fromUnprefixed('1');
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->build(),
            [],
        ));

        $enrichedActivity = $this->enrichedActivityRepository->find($activityId);

        $this->assertEquals($activityId, $enrichedActivity->getActivity()->getId());
        $this->assertNull($enrichedActivity->getNormalizedPower());
        $this->assertNull($enrichedActivity->getMaxCadence());
        $this->assertNull($enrichedActivity->getGearName());
        $this->assertFalse($enrichedActivity->hasDetailedPowerData());
        $this->assertNull($enrichedActivity->getBestAveragePowerForTimeInterval(5));
    }

    public function testFindForAnActivityThatDoesNotExist(): void
    {
        $this->expectException(EntityNotFound::class);
        $this->expectExceptionMessageIsOrContains('Activity "activity-1" not found');

        $this->enrichedActivityRepository->find(ActivityId::fromUnprefixed('1'));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->enrichedActivityRepository = $this->getContainer()->get(EnrichedActivityRepository::class);
    }
}
