<?php

namespace App\Tests\Domain\Segment\SegmentEffort;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Scan\ActivityScan;
use App\Domain\Activity\Scan\ActivityScanRepository;
use App\Domain\Activity\Scan\ActivityScanType;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Segment\SegmentType;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\Domain\Segment\SegmentBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

class RescanCustomSegmentsListenerTest extends ContainerTestCase
{
    private const string FIXTURES = __DIR__.'/Matching/fixtures/';

    private ActivityRepository $activityRepository;
    private ActivityStreamRepository $activityStreamRepository;
    private ActivityScanRepository $activityScanRepository;
    private SegmentRepository $segmentRepository;
    private SegmentEffortRepository $segmentEffortRepository;

    public function testItRescansWhenTheSportTypeChanges(): void
    {
        $segmentPolyline = EncodedPolyline::fromString(file_get_contents(self::FIXTURES.'segment-polyline.txt') ?: '');
        $this->segmentRepository->add(SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('custom-ride'))
            ->withType(SegmentType::CUSTOM)
            ->withSportType(SportType::RIDE)
            ->withPolyline($segmentPolyline)
            ->build());
        $this->segmentRepository->add(SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('custom-run'))
            ->withType(SegmentType::CUSTOM)
            ->withSportType(SportType::RUN)
            ->withPolyline($segmentPolyline)
            ->build());
        $this->segmentRepository->add(SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('custom-run-without-polyline'))
            ->withType(SegmentType::CUSTOM)
            ->withSportType(SportType::RUN)
            ->build());
        $this->segmentRepository->add(SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('imported'))
            ->withType(SegmentType::IMPORTED)
            ->withSportType(SportType::RIDE)
            ->build());

        $activity = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('1'))
            ->withSportType(SportType::RIDE)
            ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 10:00:00'))
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $streams = Json::decode(file_get_contents(self::FIXTURES.'exact-pass.json') ?: '');
        foreach ([StreamType::LAT_LNG, StreamType::TIME, StreamType::DISTANCE, StreamType::WATTS, StreamType::HEART_RATE] as $streamType) {
            $this->activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activity->getId())
                ->withStreamType($streamType)
                ->withData($streams[$streamType->value])
                ->build());
        }
        $this->segmentEffortRepository->add(SegmentEffortBuilder::fromDefaults()
            ->withSegmentEffortId(SegmentEffortId::fromUnprefixed('on-custom-ride'))
            ->withSegmentId(SegmentId::fromUnprefixed('custom-ride'))
            ->withActivityId($activity->getId())
            ->build());
        $this->segmentEffortRepository->add(SegmentEffortBuilder::fromDefaults()
            ->withSegmentEffortId(SegmentEffortId::fromUnprefixed('on-imported'))
            ->withSegmentId(SegmentId::fromUnprefixed('imported'))
            ->withActivityId($activity->getId())
            ->build());
        $this->activityScanRepository->add(ActivityScan::create(activityId: $activity->getId(), type: ActivityScanType::CUSTOM_SEGMENT, subjectId: 'segment-custom-ride'));
        $this->activityScanRepository->add(ActivityScan::create(activityId: $activity->getId(), type: ActivityScanType::STREAMS));

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withSportType(SportType::RUN),
            [],
        ));

        $this->assertEquals(
            [
                ['segmentId' => 'segment-custom-run', 'startDateTime' => '2025-01-01 10:00:37', 'elapsedTimeInSeconds' => 125.0],
                ['segmentId' => 'segment-imported', 'startDateTime' => '2023-10-10 00:00:00', 'elapsedTimeInSeconds' => 9.3],
            ],
            $this->getConnection()->executeQuery(
                'SELECT segmentId, startDateTime, elapsedTimeInSeconds FROM SegmentEffort ORDER BY segmentId'
            )->fetchAllAssociative(),
        );
        $this->assertEquals(
            [
                ['type' => 'customSegment', 'subjectId' => 'segment-custom-run'],
                ['type' => 'streams', 'subjectId' => ''],
            ],
            $this->getConnection()->executeQuery('SELECT type, subjectId FROM ActivityScan ORDER BY type')->fetchAllAssociative(),
        );
    }

    /**
     * @param array<int, mixed>|null $coordinates
     */
    #[DataProvider('provideActivitiesWithoutCoordinates')]
    public function testItOnlyRemovesWhenTheActivityHasNoCoordinates(?array $coordinates): void
    {
        $this->segmentRepository->add(SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('custom-run'))
            ->withType(SegmentType::CUSTOM)
            ->withSportType(SportType::RUN)
            ->withPolyline(EncodedPolyline::fromString(file_get_contents(self::FIXTURES.'segment-polyline.txt') ?: ''))
            ->build());
        $activity = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('1'))
            ->withSportType(SportType::RIDE)
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        if (null !== $coordinates) {
            $this->activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activity->getId())
                ->withStreamType(StreamType::LAT_LNG)
                ->withData($coordinates)
                ->build());
        }
        $this->activityScanRepository->add(ActivityScan::create(activityId: $activity->getId(), type: ActivityScanType::CUSTOM_SEGMENT, subjectId: 'segment-custom-ride'));

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withSportType(SportType::RUN),
            [],
        ));

        $this->assertEmpty($this->getConnection()->executeQuery('SELECT type FROM ActivityScan')->fetchFirstColumn());
    }

    /**
     * @return \Generator<string, array{array<int, mixed>|null}>
     */
    public static function provideActivitiesWithoutCoordinates(): \Generator
    {
        yield 'without coordinate stream' => [null];
        yield 'with empty coordinate stream' => [[]];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $this->activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        $this->activityScanRepository = $this->getContainer()->get(ActivityScanRepository::class);
        $this->segmentRepository = $this->getContainer()->get(SegmentRepository::class);
        $this->segmentEffortRepository = $this->getContainer()->get(SegmentEffortRepository::class);
    }
}
