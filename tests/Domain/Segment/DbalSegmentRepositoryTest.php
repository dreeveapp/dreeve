<?php

namespace App\Tests\Domain\Segment;

use App\Domain\Segment\DbalSegmentRepository;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Segment\Segments;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Repository\Pagination;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\String\Name;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Segment\SegmentEffort\SegmentEffortBuilder;
use Spatie\Snapshots\MatchesSnapshots;

class DbalSegmentRepositoryTest extends ContainerTestCase
{
    use MatchesSnapshots;
    private SegmentRepository $segmentRepository;

    public function testFindAndSave(): void
    {
        $segment = SegmentBuilder::fromDefaults()
            ->build();
        $this->segmentRepository->add($segment);

        $this->assertEquals(
            $segment,
            $this->segmentRepository->find($segment->getId())
        );
    }

    public function testUpdate(): void
    {
        $segment = SegmentBuilder::fromDefaults()
            ->withIsFavourite(false)
            ->build();
        $this->segmentRepository->add($segment);

        $this->segmentRepository->update(
            $segment
                ->updateIsFavourite(true)
                ->withPolyline(EncodedPolyline::fromString('pr_fA{ybz^wA_DeAgE@sAr@mEyBkFLo@f@_@~CC\qEZi@~@SnALnDlBJ~B|Bz@aAbDRn@j@ZfBHrEa@rAaAz@sABq@_AgBKeARm@hCwBTsAKgBiBmFoCqCkBkAOkAlA}EhEeBz@}AJ_BS_B]s@oCeCUs@xB_CI{CnI_T^_BHgCQiAc@k@iHyC{@aBQqBlGoNbAsDvCw@bCr@dAEv@i@bB{CxCGrBgAd@}A?cBcDoFd@gA`Ao@`AGz@d@lFvIdAl@h@E|@u@bAsD`@g@fBZ~@`ABhE`@h@pB`ARdAWp@eC`Aa@bAC`KiAvNEjDvDzE`@fBZRxFh@~L{C~Ah@x@jF\bN_@|AgB|AK`B^tFt@nB~An@zBKzAn@lD`@jBxA`@pCfA`AdAc@vCaJnAmAfANtAbA`@pC~EVlCfDnCGhAq@`@{@LuAMq@yBaBgA@aA\sBhCgEEm@WYgC_BgAeAKmAfAyCbJaAf@cAeAa@kCgBuAsDg@_Bo@_CH_Bq@o@{BSwGPs@nBoBP_Dq@gNc@aC_By@aM|CyFe@_@Qc@kBqCwCe@aArAsSBsKh@_AtBs@^_A_@qAiC}ACiEoAmAiBSa@j@}@nD{@t@e@DkAy@eFmIaAe@}@FqAx@a@~@Pt@pCdE@tAa@`BuBdAuCDkBbDk@`@iABuBs@kDz@uAvEyFdMDjB`ApBfA|@~E|Aj@v@NfAg@rEmI`TKt@X|A_ChCZfAdDpDRjBMvA_ArAaE~Ak@lAg@jCPxAvDpCxAlBpA~DJxBa@~AoBpA]p@FjAdA`CkAvBw@n@kE`@oBCo@[OcAbCwEpBiA~Ca@lCmAh@i@\cBSoCoAiDwAmBwDqCMiA`@aCl@wAfEeBz@sANiBWgBmDuDQw@|BaCUaBH{@nIaTXmAJoCSiAe@m@cFaBiA}@_AuBC_BjG_NpAcEnCm@vBr@jAAz@m@tAsChDOpBeAb@yAEoB_D_F\gAnAw@~@Ez@d@|E`ItA`Ah@Gz@w@|AuE|APhA|@Pv@E~ClCfBTjA[r@aCz@a@hA?lKwAdSn@tApCvCXzA\VvFj@~L{Cx@Hj@f@l@lDh@rMMdCqBhBSv@PrFZtB^t@vAn@lCGvAl@rDf@~AhAd@pCjAhAhAq@rCuIfAiAnALnA|@j@zCdFVnBvCd@PxBK`Ag@h@}@NyAs@aBqB}@mBf@}A~BkAB'))
                ->flagDetailsAsImported()
        );

        $this->assertMatchesJsonSnapshot(
            $this->getConnection()->executeQuery('SELECT * FROM Segment')->fetchAllAssociative()
        );
    }

    public function testItShouldThrowWhenNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('Segment "segment-1" not found'));
        $this->segmentRepository->find(SegmentId::fromUnprefixed('1'));
    }

    public function testFindAll(): void
    {
        $segmentOne = SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed(1))
            ->withName(Name::fromString('A name'))
            ->build();
        $this->segmentRepository->add($segmentOne);
        $this->getContainer()->get(SegmentEffortRepository::class)->add(
            SegmentEffortBuilder::fromDefaults()
            ->withSegmentEffortId(SegmentEffortId::fromUnprefixed(1))
            ->withSegmentId($segmentOne->getId())
            ->build()
        );
        $this->getContainer()->get(SegmentEffortRepository::class)->add(
            SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed(2))
                ->withSegmentId($segmentOne->getId())
                ->build()
        );
        $segmentTwo = SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed(2))
            ->withName(Name::fromString('C name'))
            ->build();
        $this->segmentRepository->add($segmentTwo);
        $segmentThree = SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed(3))
            ->withName(Name::fromString('B name'))
            ->build();
        $this->segmentRepository->add($segmentThree);
        $this->getContainer()->get(SegmentEffortRepository::class)->add(
            SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed(3))
                ->withSegmentId($segmentThree->getId())
                ->build()
        );

        $this->assertEquals(
            Segments::fromArray([$segmentOne, $segmentThree, $segmentTwo]),
            $this->segmentRepository->findAll(Pagination::fromOffsetAndLimit(0, 100))
        );
    }

    public function testFindSegmentsIdsMissingDetails(): void
    {
        $segmentOne = SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed(1))
            ->withDetailsHaveBeenImported(true)
            ->withName(Name::fromString('A name'))
            ->build();
        $this->segmentRepository->add($segmentOne);
        $segmentTwo = SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed(2))
            ->withDetailsHaveBeenImported(false)
            ->withName(Name::fromString('C name'))
            ->build();
        $this->segmentRepository->add($segmentTwo);
        $segmentThree = SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed(3))
            ->withDetailsHaveBeenImported(false)
            ->withName(Name::fromString('B name'))
            ->build();
        $this->segmentRepository->add($segmentThree);

        $this->assertEquals(
            [$segmentTwo->getId(), $segmentThree->getId()],
            $this->segmentRepository->findSegmentsIdsMissingDetails()
        );
    }

    public function testCount(): void
    {
        $segmentOne = SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed(1))
            ->withName(Name::fromString('A name'))
            ->build();
        $this->segmentRepository->add($segmentOne);
        $this->getContainer()->get(SegmentEffortRepository::class)->add(
            SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed(1))
                ->withSegmentId($segmentOne->getId())
                ->build()
        );
        $this->getContainer()->get(SegmentEffortRepository::class)->add(
            SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed(2))
                ->withSegmentId($segmentOne->getId())
                ->build()
        );
        $segmentTwo = SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed(2))
            ->withName(Name::fromString('C name'))
            ->build();
        $this->segmentRepository->add($segmentTwo);
        $segmentThree = SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed(3))
            ->withName(Name::fromString('B name'))
            ->build();
        $this->segmentRepository->add($segmentThree);
        $this->getContainer()->get(SegmentEffortRepository::class)->add(
            SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed(3))
                ->withSegmentId($segmentThree->getId())
                ->build()
        );

        $this->assertEquals(
            3,
            $this->segmentRepository->count()
        );
    }

    public function testDeleteOrphaned(): void
    {
        $segmentOne = SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed(1))
            ->withName(Name::fromString('A name'))
            ->build();
        $this->segmentRepository->add($segmentOne);
        $this->getContainer()->get(SegmentEffortRepository::class)->add(
            SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed(1))
                ->withSegmentId($segmentOne->getId())
                ->build()
        );
        $this->getContainer()->get(SegmentEffortRepository::class)->add(
            SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed(2))
                ->withSegmentId($segmentOne->getId())
                ->build()
        );
        $segmentTwo = SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed(2))
            ->withName(Name::fromString('C name'))
            ->build();
        $this->segmentRepository->add($segmentTwo);
        $segmentThree = SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed(3))
            ->withName(Name::fromString('B name'))
            ->build();
        $this->segmentRepository->add($segmentThree);
        $this->getContainer()->get(SegmentEffortRepository::class)->add(
            SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed(3))
                ->withSegmentId($segmentThree->getId())
                ->build()
        );

        $this->segmentRepository->deleteOrphaned();

        $this->assertEquals(
            Segments::fromArray([$segmentOne, $segmentThree]),
            $this->segmentRepository->findAll(Pagination::fromOffsetAndLimit(0, 100))
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->segmentRepository = new DbalSegmentRepository(
            $this->getConnection()
        );
    }
}
