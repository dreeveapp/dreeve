<?php

namespace App\Tests\Domain\Activity\Scan;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Scan\ActivityScan;
use App\Domain\Activity\Scan\ActivityScanRepository;
use App\Domain\Activity\Scan\ActivityScanType;
use App\Domain\Activity\Scan\DbalActivityScanRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;

class DbalActivityScanRepositoryTest extends ContainerTestCase
{
    private ActivityScanRepository $activityScanRepository;

    public function testAddIgnoresDuplicates(): void
    {
        $this->activityScanRepository->add(ActivityScan::create(activityId: ActivityId::fromUnprefixed('1'), type: ActivityScanType::CUSTOM_SEGMENT, subjectId: 'segment-1'));
        $this->activityScanRepository->add(ActivityScan::create(activityId: ActivityId::fromUnprefixed('1'), type: ActivityScanType::CUSTOM_SEGMENT, subjectId: 'segment-1'));
        $this->activityScanRepository->add(ActivityScan::create(activityId: ActivityId::fromUnprefixed('2'), type: ActivityScanType::CUSTOM_SEGMENT, subjectId: 'segment-1'));

        $this->assertSame(
            [
                ['activityId' => 'activity-1', 'type' => 'customSegment', 'subjectId' => 'segment-1'],
                ['activityId' => 'activity-2', 'type' => 'customSegment', 'subjectId' => 'segment-1'],
            ],
            $this->fetchScans(),
        );
    }

    public function testIsScanned(): void
    {
        $this->activityScanRepository->add(ActivityScan::create(activityId: ActivityId::fromUnprefixed('1'), type: ActivityScanType::CUSTOM_SEGMENT, subjectId: 'segment-1'));

        $this->assertTrue($this->activityScanRepository->isScanned(activityId: ActivityId::fromUnprefixed('1'), type: ActivityScanType::CUSTOM_SEGMENT, subjectId: 'segment-1'));
        $this->assertFalse($this->activityScanRepository->isScanned(activityId: ActivityId::fromUnprefixed('1'), type: ActivityScanType::CUSTOM_SEGMENT, subjectId: 'segment-2'));
        $this->assertFalse($this->activityScanRepository->isScanned(activityId: ActivityId::fromUnprefixed('2'), type: ActivityScanType::CUSTOM_SEGMENT, subjectId: 'segment-1'));
    }

    public function testDeleteForActivity(): void
    {
        $this->activityScanRepository->add(ActivityScan::create(activityId: ActivityId::fromUnprefixed('1'), type: ActivityScanType::CUSTOM_SEGMENT, subjectId: 'segment-1'));
        $this->activityScanRepository->add(ActivityScan::create(activityId: ActivityId::fromUnprefixed('1'), type: ActivityScanType::CUSTOM_SEGMENT, subjectId: 'segment-2'));
        $this->activityScanRepository->add(ActivityScan::create(activityId: ActivityId::fromUnprefixed('2'), type: ActivityScanType::CUSTOM_SEGMENT, subjectId: 'segment-1'));

        $this->activityScanRepository->deleteForActivity(ActivityId::fromUnprefixed('1'));

        $this->assertSame(
            [['activityId' => 'activity-2', 'type' => 'customSegment', 'subjectId' => 'segment-1']],
            $this->fetchScans(),
        );
    }

    public function testDeleteForSubject(): void
    {
        $this->activityScanRepository->add(ActivityScan::create(activityId: ActivityId::fromUnprefixed('1'), type: ActivityScanType::CUSTOM_SEGMENT, subjectId: 'segment-1'));
        $this->activityScanRepository->add(ActivityScan::create(activityId: ActivityId::fromUnprefixed('2'), type: ActivityScanType::CUSTOM_SEGMENT, subjectId: 'segment-1'));
        $this->activityScanRepository->add(ActivityScan::create(activityId: ActivityId::fromUnprefixed('1'), type: ActivityScanType::CUSTOM_SEGMENT, subjectId: 'segment-2'));

        $this->activityScanRepository->deleteForSubject(type: ActivityScanType::CUSTOM_SEGMENT, subjectId: 'segment-1');

        $this->assertSame(
            [['activityId' => 'activity-1', 'type' => 'customSegment', 'subjectId' => 'segment-2']],
            $this->fetchScans(),
        );
    }

    public function testFindActivityIdsThatNeedScanning(): void
    {
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);

        foreach ([
            ['not-scanned', SportType::RIDE, '2025-01-03', [StreamType::LAT_LNG, StreamType::TIME]],
            ['scanned', SportType::RIDE, '2025-01-01', [StreamType::LAT_LNG, StreamType::TIME]],
            ['scanned-for-other-subject', SportType::GRAVEL_RIDE, '2025-01-02', [StreamType::LAT_LNG, StreamType::TIME]],
            ['without-time', SportType::RIDE, '2025-01-04', [StreamType::LAT_LNG]],
            ['empty-time', SportType::RIDE, '2025-01-05', [StreamType::LAT_LNG]],
            ['other-activity-type', SportType::RUN, '2025-01-06', [StreamType::LAT_LNG, StreamType::TIME]],
        ] as [$id, $sportType, $startDateTime, $streamTypes]) {
            $activityRepository->add(ActivityWithRawData::fromState(
                activity: ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($id))
                    ->withSportType($sportType)
                    ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                    ->build(),
                rawData: []
            ));
            foreach ($streamTypes as $streamType) {
                $activityStreamRepository->add(
                    ActivityStreamBuilder::fromDefaults()
                        ->withActivityId(ActivityId::fromUnprefixed($id))
                        ->withStreamType($streamType)
                        ->withData([1])
                        ->build()
                );
            }
        }
        $activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('empty-time'))
                ->withStreamType(StreamType::TIME)
                ->withData([])
                ->build()
        );
        $this->activityScanRepository->add(ActivityScan::create(activityId: ActivityId::fromUnprefixed('scanned'), type: ActivityScanType::CUSTOM_SEGMENT, subjectId: 'segment-1'));
        $this->activityScanRepository->add(ActivityScan::create(activityId: ActivityId::fromUnprefixed('scanned-for-other-subject'), type: ActivityScanType::CUSTOM_SEGMENT, subjectId: 'segment-2'));

        $this->assertEquals(
            ActivityIds::fromArray([
                ActivityId::fromUnprefixed('scanned-for-other-subject'),
                ActivityId::fromUnprefixed('not-scanned'),
            ]),
            $this->activityScanRepository->findActivityIdsThatNeedScanning(
                type: ActivityScanType::CUSTOM_SEGMENT,
                subjectId: 'segment-1',
                sportTypes: ActivityType::RIDE->getSportTypes(),
                requiredStreamTypes: [StreamType::LAT_LNG, StreamType::TIME],
            ),
        );
    }

    /**
     * @return list<array<string, string>>
     */
    private function fetchScans(): array
    {
        return $this->getConnection()
            ->executeQuery('SELECT activityId, type, subjectId FROM ActivityScan ORDER BY activityId, type, subjectId')
            ->fetchAllAssociative();
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityScanRepository = new DbalActivityScanRepository($this->getConnection());
    }
}
