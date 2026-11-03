<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Lap;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Lap\ActivityLapId;
use App\Domain\Activity\Lap\ActivityLapRepository;
use App\Domain\Activity\Lap\ActivityLaps;
use App\Domain\Activity\Lap\DbalActivityLapRepository;
use App\Tests\ContainerTestCase;

class DbalActivityLapRepositoryTest extends ContainerTestCase
{
    private ActivityLapRepository $activityLapRepository;

    public function testAddAndFindBy(): void
    {
        $activityLapTwo = ActivityLapBuilder::fromDefaults()
            ->withLapId(ActivityLapId::fromUnprefixed('test'))
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->withLapNumber(2)
            ->build();
        $this->activityLapRepository->add($activityLapTwo);

        $activityLapOne = ActivityLapBuilder::fromDefaults()
            ->withLapId(ActivityLapId::fromUnprefixed('test2'))
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->withLapNumber(1)
            ->build();
        $this->activityLapRepository->add($activityLapOne);

        $activityLapThree = ActivityLapBuilder::fromDefaults()
            ->withLapId(ActivityLapId::fromUnprefixed('test3'))
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->withLapNumber(3)
            ->build();
        $this->activityLapRepository->add($activityLapThree);

        $this->activityLapRepository->add(ActivityLapBuilder::fromDefaults()
            ->withLapId(ActivityLapId::fromUnprefixed('test4'))
            ->withActivityId(ActivityId::fromUnprefixed('test2'))
            ->withLapNumber(3)
            ->build());

        $this->assertEquals(
            ActivityLaps::fromArray([$activityLapOne, $activityLapTwo, $activityLapThree]),
            $this->activityLapRepository->findBy(
                activityId: ActivityId::fromUnprefixed('test'),
            )
        );
    }

    public function testIsImportedForActivity(): void
    {
        $this->activityLapRepository->add(ActivityLapBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->withLapNumber(3)
            ->build()
        );

        $this->assertTrue($this->activityLapRepository->isImportedForActivity(ActivityId::fromUnprefixed('test')));
        $this->assertFalse($this->activityLapRepository->isImportedForActivity(ActivityId::fromUnprefixed('test2')));
    }

    public function testDeleteForActivity(): void
    {
        $this->activityLapRepository->add(ActivityLapBuilder::fromDefaults()
            ->withLapId(ActivityLapId::fromUnprefixed('test'))
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->withLapNumber(1)
            ->build());

        $this->activityLapRepository->add(ActivityLapBuilder::fromDefaults()
            ->withLapId(ActivityLapId::fromUnprefixed('test2'))
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->withLapNumber(2)
            ->build());

        $this->activityLapRepository->add(ActivityLapBuilder::fromDefaults()
            ->withLapId(ActivityLapId::fromUnprefixed('test3'))
            ->withActivityId(ActivityId::fromUnprefixed('test'))
            ->withLapNumber(3)
            ->build());

        $this->activityLapRepository->add(ActivityLapBuilder::fromDefaults()
            ->withLapId(ActivityLapId::fromUnprefixed('test4'))
            ->withActivityId(ActivityId::fromUnprefixed('test2'))
            ->withLapNumber(3)
            ->build());

        $this->activityLapRepository->deleteForActivity(ActivityId::fromUnprefixed('test'));

        $this->assertEquals(
            1,
            $this->getConnection()->executeQuery('SELECT COUNT(*) FROM ActivityLap')->fetchOne()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityLapRepository = new DbalActivityLapRepository($this->getConnection());
    }
}
