<?php

namespace App\Tests\Domain\Segment\SegmentActivityScan;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Segment\SegmentActivityScan\DbalSegmentActivityScanRepository;
use App\Domain\Segment\SegmentActivityScan\SegmentActivityScan;
use App\Domain\Segment\SegmentActivityScan\SegmentActivityScanRepository;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentType;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\Domain\Segment\SegmentBuilder;

class DbalSegmentActivityScanRepositoryTest extends ContainerTestCase
{
    private SegmentActivityScanRepository $segmentActivityScanRepository;

    public function testAddIgnoresDuplicates(): void
    {
        $this->segmentActivityScanRepository->add(SegmentActivityScan::create(SegmentId::fromUnprefixed('1'), ActivityId::fromUnprefixed('1')));
        $this->segmentActivityScanRepository->add(SegmentActivityScan::create(SegmentId::fromUnprefixed('1'), ActivityId::fromUnprefixed('1')));
        $this->segmentActivityScanRepository->add(SegmentActivityScan::create(SegmentId::fromUnprefixed('1'), ActivityId::fromUnprefixed('2')));

        $this->assertSame(
            [
                ['segmentId' => 'segment-1', 'activityId' => 'activity-1'],
                ['segmentId' => 'segment-1', 'activityId' => 'activity-2'],
            ],
            $this->fetchScans(),
        );
    }

    public function testDeleteForActivity(): void
    {
        $this->segmentActivityScanRepository->add(SegmentActivityScan::create(SegmentId::fromUnprefixed('1'), ActivityId::fromUnprefixed('1')));
        $this->segmentActivityScanRepository->add(SegmentActivityScan::create(SegmentId::fromUnprefixed('2'), ActivityId::fromUnprefixed('1')));
        $this->segmentActivityScanRepository->add(SegmentActivityScan::create(SegmentId::fromUnprefixed('1'), ActivityId::fromUnprefixed('2')));

        $this->segmentActivityScanRepository->deleteForActivity(ActivityId::fromUnprefixed('1'));

        $this->assertSame(
            [['segmentId' => 'segment-1', 'activityId' => 'activity-2']],
            $this->fetchScans(),
        );
    }

    public function testDeleteForSegment(): void
    {
        $this->segmentActivityScanRepository->add(SegmentActivityScan::create(SegmentId::fromUnprefixed('1'), ActivityId::fromUnprefixed('1')));
        $this->segmentActivityScanRepository->add(SegmentActivityScan::create(SegmentId::fromUnprefixed('1'), ActivityId::fromUnprefixed('2')));
        $this->segmentActivityScanRepository->add(SegmentActivityScan::create(SegmentId::fromUnprefixed('2'), ActivityId::fromUnprefixed('1')));

        $this->segmentActivityScanRepository->deleteForSegment(SegmentId::fromUnprefixed('1'));

        $this->assertSame(
            [['segmentId' => 'segment-2', 'activityId' => 'activity-1']],
            $this->fetchScans(),
        );
    }

    public function testFindActivityIdsThatNeedScanning(): void
    {
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        $segment = SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('custom'))
            ->withType(SegmentType::CUSTOM)
            ->withSportType(SportType::RIDE)
            ->build();

        foreach ([
            ['not-scanned', SportType::RIDE, '2025-01-03', true],
            ['scanned', SportType::RIDE, '2025-01-01', true],
            ['scanned-for-other-segment', SportType::GRAVEL_RIDE, '2025-01-02', true],
            ['without-latlng', SportType::RIDE, '2025-01-04', false],
            ['other-activity-type', SportType::RUN, '2025-01-05', true],
        ] as [$id, $sportType, $startDateTime, $hasLatLng]) {
            $activityRepository->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($id))
                    ->withSportType($sportType)
                    ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                    ->build(),
                []
            ));
            $activityStreamRepository->add(
                ActivityStreamBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($id))
                    ->withStreamType($hasLatLng ? StreamType::LAT_LNG : StreamType::TIME)
                    ->build()
            );
        }
        $this->segmentActivityScanRepository->add(SegmentActivityScan::create($segment->getId(), ActivityId::fromUnprefixed('scanned')));
        $this->segmentActivityScanRepository->add(SegmentActivityScan::create(SegmentId::fromUnprefixed('other'), ActivityId::fromUnprefixed('scanned-for-other-segment')));

        $this->assertEquals(
            ActivityIds::fromArray([
                ActivityId::fromUnprefixed('scanned-for-other-segment'),
                ActivityId::fromUnprefixed('not-scanned'),
            ]),
            $this->segmentActivityScanRepository->findActivityIdsThatNeedScanning($segment),
        );
    }

    /**
     * @return list<array<string, string>>
     */
    private function fetchScans(): array
    {
        return $this->getConnection()
            ->executeQuery('SELECT segmentId, activityId FROM SegmentActivityScan ORDER BY segmentId, activityId')
            ->fetchAllAssociative();
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->segmentActivityScanRepository = new DbalSegmentActivityScanRepository($this->getConnection());
    }
}
