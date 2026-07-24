<?php

namespace App\Tests\Domain\Activity\BestEffort;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\BestEffort\ActivityBestEffortRepository;
use App\Domain\Activity\BestEffort\DbalActivityBestEffortRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use Spatie\Snapshots\MatchesSnapshots;

class DbalActivityBestEffortRepositoryTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private ActivityBestEffortRepository $activityBestEffortRepository;

    public function testAdd(): void
    {
        $this->assertFalse($this->activityBestEffortRepository->hasData());
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test'))
                ->withSportType(SportType::RIDE)
                ->withDistanceInMeter(Meter::from(10000))
                ->withTimeInSeconds(3600)
                ->build()
        );
        $this->assertTrue($this->activityBestEffortRepository->hasData());
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-2'))
                ->withSportType(SportType::RIDE)
                ->withDistanceInMeter(Meter::from(10000))
                ->withTimeInSeconds(3600)
                ->build()
        );
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-2'))
                ->withSportType(SportType::RIDE)
                ->withDistanceInMeter(Meter::from(1000))
                ->withTimeInSeconds(3600)
                ->build()
        );

        $this->assertMatchesJsonSnapshot(Json::encode(
            $this->getConnection()->executeQuery('SELECT * FROM ActivityBestEffort')->fetchAllAssociative()
        ));
    }

    public function testFindActivityIdsThatNeedBestEffortsCalculation(): void
    {
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test'))
                ->withSportType(SportType::RIDE)
                ->withDistanceInMeter(Meter::from(10000))
                ->withTimeInSeconds(3600)
                ->build()
        );
        $this->activityBestEffortRepository->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-2'))
                ->withSportType(SportType::RIDE)
                ->withDistanceInMeter(Meter::from(10000))
                ->withTimeInSeconds(3600)
                ->build()
        );

        $this->getContainer()->get(ActivityRepository::class)->add(
            ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('test-2'))
                    ->build(),
                []
            )
        );

        $this->getContainer()->get(ActivityRepository::class)->add(
            ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('test-4'))
                    ->build(),
                []
            )
        );
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-4'))
                ->withStreamType(StreamType::DISTANCE)
                ->withData([1, 10000])
                ->build()
        );
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-4'))
                ->withStreamType(StreamType::TIME)
                ->withData([1, 2, 3, 4, 5])
                ->build()
        );

        $this->assertEquals(
            ActivityIds::fromArray([ActivityId::fromUnprefixed('test-4')]),
            $this->activityBestEffortRepository->findActivityIdsThatNeedBestEffortsCalculation()
        );
    }

    public function testDeleteForActivity(): void
    {
        $this->activityBestEffortRepository->add(ActivityBestEffortBuilder::fromDefaults()
            ->withDistanceInMeter(Meter::from(10000))
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->build());

        $this->activityBestEffortRepository->add(ActivityBestEffortBuilder::fromDefaults()
            ->withDistanceInMeter(Meter::from(1000))
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->build());

        $this->activityBestEffortRepository->add(ActivityBestEffortBuilder::fromDefaults()
            ->withDistanceInMeter(Meter::from(000))
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->build());

        $this->activityBestEffortRepository->add(ActivityBestEffortBuilder::fromDefaults()
            ->withDistanceInMeter(Meter::from(10000))
            ->withActivityId(ActivityId::fromUnprefixed('test2'))
            ->build());

        $this->activityBestEffortRepository->deleteForActivity(ActivityId::fromUnprefixed('test'));

        $this->assertEquals(
            1,
            $this->getConnection()->executeQuery('SELECT COUNT(*) FROM ActivityBestEffort')->fetchOne()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->activityBestEffortRepository = new DbalActivityBestEffortRepository(
            $this->getConnection(),
        );
    }
}
