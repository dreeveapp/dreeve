<?php

namespace App\Tests\Domain\Activity\BestEffort;

use App\Domain\Activity\ActivityId;
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
use PHPUnit\Framework\Attributes\DataProvider;

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

    /**
     * @param array<int, int>|null $streamData
     */
    #[DataProvider('provideActivitiesWithoutRecalculableBestEfforts')]
    public function testItOnlyRemovesWhenBestEffortsCannotBeRecalculated(SportType $newSportType, ?array $streamData): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('1'))
            ->withSportType(SportType::RUN)
            ->withDistance(Kilometer::from(12))
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        if (null !== $streamData) {
            foreach ([StreamType::DISTANCE, StreamType::TIME] as $streamType) {
                $this->activityStreamRepository->add(
                    ActivityStreamBuilder::fromDefaults()
                        ->withActivityId($activity->getId())
                        ->withStreamType($streamType)
                        ->withData($streamData)
                        ->build()
                );
            }
        }
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId($activity->getId())
                ->withSportType(SportType::RUN)
                ->build()
        );

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withSportType($newSportType),
            [],
        ));

        $this->assertFalse($this->activityBestEffortRepository->hasData());
    }

    /**
     * @return \Generator<string, array{SportType, array<int, int>|null}>
     */
    public static function provideActivitiesWithoutRecalculableBestEfforts(): \Generator
    {
        yield 'sport type without best efforts' => [SportType::WALK, range(0, 12000, 10)];
        yield 'without streams' => [SportType::RIDE, null];
        yield 'with empty streams' => [SportType::RIDE, []];
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
