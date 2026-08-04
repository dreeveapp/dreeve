<?php

namespace App\Tests\Domain\Gear;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\DbalGearIdRepository;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIdRepository;
use App\Domain\Gear\GearRepository;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class DbalGearIdRepositoryTest extends ContainerTestCase
{
    private GearIdRepository $gearIdRepository;

    public function testFindAllAndFindRetired(): void
    {
        $this->assertEmpty($this->gearIdRepository->findAll()->toArray());
        $this->assertEmpty($this->gearIdRepository->findRetired()->toArray());

        $gearRepository = $this->getContainer()->get(GearRepository::class);
        $gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed(1))
                ->withIsRetired(false)
                ->build()
        );
        $gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed(2))
                ->withIsRetired(true)
                ->build()
        );

        $this->assertEquals(
            [GearId::fromUnprefixed(1), GearId::fromUnprefixed(2)],
            $this->gearIdRepository->findAll()->toArray()
        );
        $this->assertEquals(
            [GearId::fromUnprefixed(2)],
            $this->gearIdRepository->findRetired()->toArray()
        );
    }

    public function testFindUniqueStravaGearIds(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->build(),
            ['gear_id' => 'b1']
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->build(),
            ['gear_id' => 'b2']
        ));

        $this->assertEquals(
            [GearId::fromUnprefixed('b1'), GearId::fromUnprefixed('b2')],
            $this->gearIdRepository->findUniqueStravaGearIds(null)->toArray()
        );

        $this->assertEquals(
            [GearId::fromUnprefixed('b1')],
            $this->gearIdRepository->findUniqueStravaGearIds(
                ActivityIds::fromArray([ActivityId::fromUnprefixed('1')])
            )->toArray()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->gearIdRepository = new DbalGearIdRepository(
            $this->getConnection(),
        );
    }
}
