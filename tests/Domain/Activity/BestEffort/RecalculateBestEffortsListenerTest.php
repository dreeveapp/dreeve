<?php

namespace App\Tests\Domain\Activity\BestEffort;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityName;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\BestEffort\ActivityBestEffortRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Length\Mile;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;

class RecalculateBestEffortsListenerTest extends ContainerTestCase
{
    private ActivityRepository $activityRepository;
    private ActivityStreamRepository $activityStreamRepository;
    private ActivityBestEffortRepository $activityBestEffortRepository;

    public function testItRecalculatesWhenTheSportTypeChanges(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('1'))
            ->withSportType(SportType::RUN)
            ->withDistance(Kilometer::from(12))
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activity->getId())
                ->withStreamType(StreamType::DISTANCE)
                ->withData(range(0, 12000, 10))
                ->build()
        );
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activity->getId())
                ->withStreamType(StreamType::TIME)
                ->withData(range(0, 1200))
                ->build()
        );
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId($activity->getId())
                ->withSportType(SportType::RUN)
                ->withDistanceInMeter(Meter::from(400))
                ->build()
        );

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withSportType(SportType::RIDE),
            [],
        ));

        $this->assertEquals(
            [
                ['sportType' => SportType::RIDE->value, 'distanceInMeter' => Mile::from(5)->toMeter()->toInt(), 'timeInSeconds' => 805],
                ['sportType' => SportType::RIDE->value, 'distanceInMeter' => 10000, 'timeInSeconds' => 1000],
            ],
            $this->getConnection()->executeQuery(
                'SELECT sportType, distanceInMeter, timeInSeconds FROM ActivityBestEffort ORDER BY distanceInMeter'
            )->fetchAllAssociative()
        );
    }

    public function testItOnlyRemovesWhenTheNewSportTypeDoesNotSupportBestEfforts(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('1'))
            ->withSportType(SportType::RUN)
            ->withDistance(Kilometer::from(12))
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activity->getId())
                ->withStreamType(StreamType::DISTANCE)
                ->withData(range(0, 12000, 10))
                ->build()
        );
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activity->getId())
                ->withStreamType(StreamType::TIME)
                ->withData(range(0, 1200))
                ->build()
        );
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId($activity->getId())
                ->withSportType(SportType::RUN)
                ->build()
        );

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withSportType(SportType::WALK),
            [],
        ));

        $this->assertFalse($this->activityBestEffortRepository->hasData());
    }

    public function testItOnlyRemovesWhenTheActivityHasNoStreams(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('1'))
            ->withSportType(SportType::RUN)
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId($activity->getId())
                ->withSportType(SportType::RUN)
                ->build()
        );

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withSportType(SportType::RIDE),
            [],
        ));

        $this->assertFalse($this->activityBestEffortRepository->hasData());
    }

    public function testItOnlyRemovesWhenTheStreamsAreEmpty(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('1'))
            ->withSportType(SportType::RUN)
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activity->getId())
                ->withStreamType(StreamType::DISTANCE)
                ->withData([])
                ->build()
        );
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activity->getId())
                ->withStreamType(StreamType::TIME)
                ->withData([])
                ->build()
        );
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId($activity->getId())
                ->withSportType(SportType::RUN)
                ->build()
        );

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withSportType(SportType::RIDE),
            [],
        ));

        $this->assertFalse($this->activityBestEffortRepository->hasData());
    }

    public function testItLeavesBestEffortsAloneWhenTheSportTypeDidNotChange(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('1'))
            ->withSportType(SportType::RUN)
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId($activity->getId())
                ->withSportType(SportType::RUN)
                ->build()
        );

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withName(ActivityName::fromString('Renamed')),
            [],
        ));

        $this->assertTrue($this->activityBestEffortRepository->hasData());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $this->activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        $this->activityBestEffortRepository = $this->getContainer()->get(ActivityBestEffortRepository::class);
    }
}
