<?php

namespace App\Tests\Domain\Activity\Stream\CombinedStream;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\CombinedStream\ActivityStreamCombiner;
use App\Domain\Activity\Stream\CombinedStream\CombinedActivityStreamRepository;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamType;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Measurement\UnitSystem;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;

class RecombineActivityStreamsListenerTest extends ContainerTestCase
{
    private ActivityRepository $activityRepository;
    private ActivityStreamRepository $activityStreamRepository;
    private CombinedActivityStreamRepository $combinedActivityStreamRepository;
    private ActivityStreamCombiner $activityStreamCombiner;

    public function testItRecombinesWhenTheSportTypeChanges(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('1'))
            ->withSportType(SportType::RUN)
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activity->getId())
                ->withStreamType(StreamType::TIME)
                ->withData([0, 1, 2, 3])
                ->build()
        );
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activity->getId())
                ->withStreamType(StreamType::VELOCITY)
                ->withData([3.0, 3.1, 3.2, 3.3])
                ->build()
        );
        $this->activityStreamCombiner->combineFor($activity->getId(), UnitSystem::METRIC);
        $this->activityStreamCombiner->combineFor($activity->getId(), UnitSystem::IMPERIAL);

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withSportType(SportType::RIDE),
            [],
        ));

        $this->assertTrue($this->combinedActivityStreamRepository->hasStreamTypeFor(
            $activity->getId(), UnitSystem::METRIC, CombinedStreamType::VELOCITY
        ));
        $this->assertFalse($this->combinedActivityStreamRepository->hasStreamTypeFor(
            $activity->getId(), UnitSystem::METRIC, CombinedStreamType::PACE
        ));
        $this->assertEquals(
            [UnitSystem::METRIC->value],
            $this->getConnection()->executeQuery('SELECT unitSystem FROM CombinedActivityStream')->fetchFirstColumn()
        );
    }

    public function testItOnlyRemovesWhenTheActivityHasNoStreams(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('1'))
            ->withSportType(SportType::RUN)
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->combinedActivityStreamRepository->add(
            CombinedActivityStreamBuilder::fromDefaults()
                ->withActivityId($activity->getId())
                ->build()
        );

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withSportType(SportType::RIDE),
            [],
        ));

        $this->assertEmpty(
            $this->getConnection()->executeQuery('SELECT activityId FROM CombinedActivityStream')->fetchFirstColumn()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $this->activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        $this->combinedActivityStreamRepository = $this->getContainer()->get(CombinedActivityStreamRepository::class);
        $this->activityStreamCombiner = $this->getContainer()->get(ActivityStreamCombiner::class);
    }
}
