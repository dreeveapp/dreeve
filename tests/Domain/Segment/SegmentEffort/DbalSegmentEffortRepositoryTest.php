<?php

namespace App\Tests\Domain\Segment\SegmentEffort;

use App\Domain\Activity\ActivityId;
use App\Domain\Segment\SegmentEffort\DbalSegmentEffortRepository;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentEffort\SegmentEffortRankingMap;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentEffort\SegmentEfforts;
use App\Domain\Segment\SegmentId;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use Spatie\Snapshots\MatchesSnapshots;

class DbalSegmentEffortRepositoryTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private SegmentEffortRepository $segmentEffortRepository;

    public function testFindAndSave(): void
    {
        $segmentEffort = SegmentEffortBuilder::fromDefaults()
            ->withRank(1)
            ->build();
        $this->segmentEffortRepository->add($segmentEffort);

        $this->assertEquals(
            $segmentEffort,
            $this->segmentEffortRepository->find($segmentEffort->getId())
        );
    }

    public function testItShouldThrowWhenNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('segmentEffort "segmentEffort-1" not found'));
        $this->segmentEffortRepository->find(SegmentEffortId::fromUnprefixed(1));
    }

    public function testFindTopXBySegmentId(): void
    {
        $segmentEffortOne = SegmentEffortBuilder::fromDefaults()
            ->withSegmentEffortId(SegmentEffortId::fromUnprefixed(1))
            ->withSegmentId(SegmentId::fromUnprefixed(1))
            ->withRank(1)
            ->build();
        $this->segmentEffortRepository->add($segmentEffortOne);

        $segmentEffortTwo = SegmentEffortBuilder::fromDefaults()
            ->withSegmentEffortId(SegmentEffortId::fromUnprefixed(2))
            ->withSegmentId(SegmentId::fromUnprefixed(1))
            ->withRank(2)
            ->build();
        $this->segmentEffortRepository->add($segmentEffortTwo);

        $segmentEffortThree = SegmentEffortBuilder::fromDefaults()
            ->withSegmentEffortId(SegmentEffortId::fromUnprefixed(3))
            ->withSegmentId(SegmentId::fromUnprefixed(2))
            ->withRank(null)
            ->build();
        $this->segmentEffortRepository->add($segmentEffortThree);

        $this->assertEquals(
            SegmentEfforts::fromArray([$segmentEffortOne, $segmentEffortTwo]),
            $this->segmentEffortRepository->findTopXBySegmentId($segmentEffortOne->getSegmentId(), 10)
        );
    }

    public function testFindAndCountBySegmentId(): void
    {
        $segmentEffortOne = SegmentEffortBuilder::fromDefaults()
            ->withSegmentEffortId(SegmentEffortId::fromUnprefixed(1))
            ->withSegmentId(SegmentId::fromUnprefixed(1))
            ->withStartDateTime(SerializableDateTime::fromString('2026-02-02 00:00:00'))
            ->withRank(1)
            ->build();
        $this->segmentEffortRepository->add($segmentEffortOne);

        $segmentEffortTwo = SegmentEffortBuilder::fromDefaults()
            ->withSegmentEffortId(SegmentEffortId::fromUnprefixed(2))
            ->withStartDateTime(SerializableDateTime::fromString('2026-02-01 00:00:00'))
            ->withSegmentId(SegmentId::fromUnprefixed(1))
            ->withRank(2)
            ->build();
        $this->segmentEffortRepository->add($segmentEffortTwo);

        $segmentEffortThree = SegmentEffortBuilder::fromDefaults()
            ->withSegmentEffortId(SegmentEffortId::fromUnprefixed(3))
            ->withSegmentId(SegmentId::fromUnprefixed(2))
            ->withRank(null)
            ->build();
        $this->segmentEffortRepository->add($segmentEffortThree);

        $this->assertEquals(
            SegmentEfforts::fromArray([$segmentEffortOne, $segmentEffortTwo]),
            $this->segmentEffortRepository->findBySegmentId($segmentEffortOne->getSegmentId())
        );

        $this->assertEquals(
            2,
            $this->segmentEffortRepository->countBySegmentId($segmentEffortOne->getSegmentId())
        );
    }

    public function testFindByActivityId(): void
    {
        $segmentEffortOne = SegmentEffortBuilder::fromDefaults()
            ->withSegmentEffortId(SegmentEffortId::fromUnprefixed(1))
            ->withActivityId(ActivityId::fromUnprefixed(1))
            ->withRank(1)
            ->build();
        $this->segmentEffortRepository->add($segmentEffortOne);

        $segmentEffortTwo = SegmentEffortBuilder::fromDefaults()
            ->withSegmentEffortId(SegmentEffortId::fromUnprefixed(2))
            ->withActivityId(ActivityId::fromUnprefixed(1))
            ->withRank(2)
            ->build();
        $this->segmentEffortRepository->add($segmentEffortTwo);

        $segmentEffortThree = SegmentEffortBuilder::fromDefaults()
            ->withSegmentEffortId(SegmentEffortId::fromUnprefixed(3))
            ->withActivityId(ActivityId::fromUnprefixed(2))
            ->withRank(null)
            ->build();
        $this->segmentEffortRepository->add($segmentEffortThree);

        $this->assertEquals(
            SegmentEfforts::fromArray([$segmentEffortOne, $segmentEffortTwo]),
            $this->segmentEffortRepository->findByActivityId($segmentEffortOne->getActivityId())
        );
    }

    public function testFindByActivityIdRanksTheSameWayTheRankingMapDoes(): void
    {
        $rankingMap = $this->getContainer()->get(SegmentEffortRankingMap::class);

        // Two segments, ridden by three activities, so ranks only come out right when efforts
        // of other activities are taken into account.
        foreach ([[1, 1, 1, 300], [2, 1, 2, 100], [3, 1, 3, 200], [4, 2, 1, 60], [5, 2, 2, 30]] as [$effortId, $segmentId, $activityId, $elapsedTime]) {
            $this->segmentEffortRepository->add(SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed($effortId))
                ->withSegmentId(SegmentId::fromUnprefixed($segmentId))
                ->withActivityId(ActivityId::fromUnprefixed($activityId))
                ->withElapsedTimeInSeconds($elapsedTime)
                ->build());
        }

        $segmentEfforts = $this->segmentEffortRepository->findByActivityId(ActivityId::fromUnprefixed(1));

        $this->assertCount(2, $segmentEfforts);
        foreach ($segmentEfforts as $segmentEffort) {
            $this->assertEquals(
                $rankingMap->getRankFor($segmentEffort->getId()),
                $segmentEffort->getRank(),
                sprintf('Rank mismatch for segment effort "%s"', $segmentEffort->getId())
            );
        }
        $this->assertEquals([3, 2], $segmentEfforts->map(fn ($segmentEffort): ?int => $segmentEffort->getRank()));
    }

    public function testDelete(): void
    {
        $segmentEffortOne = SegmentEffortBuilder::fromDefaults()->build();
        $this->segmentEffortRepository->add($segmentEffortOne);

        $segmentEffortTwo = SegmentEffortBuilder::fromDefaults()
            ->withActivityId(ActivityId::random())
            ->withSegmentEffortId(SegmentEffortId::random())->build();
        $this->segmentEffortRepository->add($segmentEffortTwo);

        $this->assertEquals(
            2,
            $this->getConnection()->executeQuery('SELECT COUNT(*) FROM SegmentEffort')->fetchOne()
        );

        $this->segmentEffortRepository->deleteForActivity($segmentEffortOne->getActivityId());
        $this->assertEquals(
            1,
            $this->getConnection()->executeQuery('SELECT COUNT(*) FROM SegmentEffort')->fetchOne()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->segmentEffortRepository = new DbalSegmentEffortRepository(
            $this->getConnection(),
            $this->getContainer()->get(SegmentEffortRankingMap::class)
        );
    }
}
