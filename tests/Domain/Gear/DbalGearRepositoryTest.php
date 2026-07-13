<?php

namespace App\Tests\Domain\Gear;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityTypes;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\DbalGearRepository;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Gears;
use App\Domain\Gear\GearType;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Measurement\Time\Seconds;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use Money\Money;
use Spatie\Snapshots\MatchesSnapshots;

class DbalGearRepositoryTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private GearRepository $gearRepository;

    public function testFindAndAdd(): void
    {
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        // The stored column value (1230) must be ignored: distance is derived from activities.
        $gear = GearBuilder::fromDefaults()
            ->withGearId(GearId::fromUnprefixed(1))
            ->withDistanceInMeter(Meter::from(1230))
            ->build();
        $this->gearRepository->add($gear);

        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withGearId(GearId::fromUnprefixed(1))
                ->withDistance(Kilometer::from(5))
                ->build(),
            []
        ));

        $this->assertEquals(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed(1))
                ->withDistanceInMeter(Meter::from(5000))
                ->build(),
            $this->gearRepository->find($gear->getId())
        );
    }

    public function testItShouldPersistAndReadPurchasePrice(): void
    {
        $gear = GearBuilder::fromDefaults()
            ->withGearId(GearId::fromUnprefixed(1))
            ->withPurchasePrice(Money::EUR(150000))
            ->build();
        $this->gearRepository->add($gear);

        $this->assertEquals(
            Money::EUR(150000),
            $this->gearRepository->find($gear->getId())->getPurchasePrice()
        );
    }

    public function testItShouldThrowWhenNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('Gear "gear-1" not found'));
        $this->gearRepository->find(GearId::fromUnprefixed('1'));
    }

    public function testAddPersistsType(): void
    {
        $this->gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed(1))
                ->build()
        );
        $this->gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed(2))
                ->withGearType(GearType::CUSTOM)
                ->build()
        );

        $this->assertMatchesJsonSnapshot(
            $this->getConnection()->executeQuery('SELECT * FROM Gear ORDER BY gearId')->fetchAllAssociative()
        );
    }

    public function testUpdate(): void
    {
        $gear = GearBuilder::fromDefaults()
            ->withGearId(GearId::fromUnprefixed(1))
            ->withPurchasePrice(Money::EUR(150000))
            ->build();
        $this->gearRepository->add($gear);

        $this->gearRepository->update(
            $gear->withName('Updated gear')
                ->withIsRetired(true)
        );

        $this->assertMatchesJsonSnapshot(
            $this->getConnection()->executeQuery('SELECT * FROM Gear')->fetchAllAssociative()
        );
    }

    public function testFindAll(): void
    {
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        $gearOne = GearBuilder::fromDefaults()
            ->withGearId(GearId::fromUnprefixed(1))
            ->withDistanceInMeter(Meter::from(1230))
            ->build();
        $this->gearRepository->add($gearOne);
        $gearTwo = GearBuilder::fromDefaults()
            ->withGearId(GearId::fromUnprefixed(2))
            ->withGearType(GearType::CUSTOM)
            ->withDistanceInMeter(Meter::from(10230))
            ->build();
        $this->gearRepository->add($gearTwo);
        $gearThree = GearBuilder::fromDefaults()
            ->withGearId(GearId::fromUnprefixed(3))
            ->withDistanceInMeter(Meter::from(230))
            ->build();
        $this->gearRepository->add($gearThree);
        $gearFour = GearBuilder::fromDefaults()
            ->withGearId(GearId::fromUnprefixed(4))
            ->withGearType(GearType::CUSTOM)
            ->withDistanceInMeter(Meter::from(100230))
            ->withIsRetired(true)
            ->build();
        $this->gearRepository->add($gearFour);

        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withGearId(GearId::fromUnprefixed(1))
                ->withDistance(Kilometer::from(10))
                ->withElevation(Meter::from(100))
                ->withMovingTimeInSeconds(3600)
                ->build(),
            []
        ));
        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withGearId(GearId::fromUnprefixed(1))
                ->withDistance(Kilometer::from(20))
                ->withElevation(Meter::from(200))
                ->withMovingTimeInSeconds(7200)
                ->build(),
            []
        ));
        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withGearId(GearId::fromUnprefixed(2))
                ->withDistance(Kilometer::from(5))
                ->withElevation(Meter::from(50))
                ->withMovingTimeInSeconds(1800)
                ->build(),
            []
        ));

        $this->assertEquals(
            Gears::fromArray([
                GearBuilder::fromDefaults()
                    ->withGearId(GearId::fromUnprefixed(1))
                    ->withDistanceInMeter(Meter::from(30000))
                    ->withMovingTime(Seconds::from(10800))
                    ->withElevation(Meter::from(300))
                    ->withNumberOfActivities(2)
                    ->build()
                    ->withActivityTypes(ActivityTypes::fromArray([ActivityType::RIDE])),
                GearBuilder::fromDefaults()
                    ->withGearId(GearId::fromUnprefixed(2))
                    ->withGearType(GearType::CUSTOM)
                    ->withDistanceInMeter(Meter::from(5000))
                    ->withMovingTime(Seconds::from(1800))
                    ->withElevation(Meter::from(50))
                    ->withNumberOfActivities(1)
                    ->build()
                    ->withActivityTypes(ActivityTypes::fromArray([ActivityType::RIDE])),
                GearBuilder::fromDefaults()
                    ->withGearId(GearId::fromUnprefixed(3))
                    ->withDistanceInMeter(Meter::zero())
                    ->build(),
                GearBuilder::fromDefaults()
                    ->withGearId(GearId::fromUnprefixed(4))
                    ->withGearType(GearType::CUSTOM)
                    ->withDistanceInMeter(Meter::zero())
                    ->withIsRetired(true)
                    ->build(),
            ]),
            $this->gearRepository->findAll()
        );
    }

    public function testHasGear(): void
    {
        $this->assertFalse($this->gearRepository->hasGear());

        $this->getContainer()->get(ActivityRepository::class)->add(
            ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withGearId(GearId::fromUnprefixed(4))
                    ->build(),
                []
            ),
        );

        $this->gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed(4))
                ->withDistanceInMeter(Meter::from(100230))
                ->withIsRetired(true)
                ->build()
        );

        $this->assertTrue($this->gearRepository->hasGear());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->gearRepository = new DbalGearRepository(
            $this->getConnection(),
        );
    }
}
