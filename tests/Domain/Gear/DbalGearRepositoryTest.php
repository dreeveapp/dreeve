<?php

namespace App\Tests\Domain\Gear;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityTypes;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Gear\DbalGearRepository;
use App\Domain\Gear\Gear;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Gears;
use App\Domain\Gear\GearType;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemPath;
use App\Domain\Media\MediaItemRepository;
use App\Infrastructure\Eventing\EventBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Media\MediaItemBuilder;
use Money\Money;

class DbalGearRepositoryTest extends ContainerTestCase
{
    private GearRepository $gearRepository;

    public function testFindAndAdd(): void
    {
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        $mediaItem = MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('bike'))
            ->withPath(MediaItemPath::fromString('gear/bike.jpg'))
            ->build();
        $this->getContainer()->get(MediaItemRepository::class)->add($mediaItem);

        // The stored column value (1230) must be ignored: distance is derived from activities.
        $gear = GearBuilder::fromDefaults()
            ->withGearId(GearId::fromUnprefixed('1'))
            ->withDistanceInMeter(Meter::from(1230))
            ->withNotes("Serial 123\nBought in Ghent")
            ->withMediaItemId(MediaItemId::fromUnprefixed('bike'))
            ->build();
        $this->gearRepository->add($gear);

        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withDistance(Kilometer::from(5))
                ->withElevation(Meter::from(50))
                ->withMovingTimeInSeconds(1800)
                ->withCalories(400)
                ->build(),
            []
        ));
        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withDistance(Kilometer::from(15))
                ->withElevation(Meter::from(150))
                ->withMovingTimeInSeconds(3600)
                ->withCalories(600)
                ->build(),
            []
        ));

        $this->assertEquals(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withDistanceInMeter(Meter::from(20000))
                ->withMovingTime(Seconds::from(5400))
                ->withElevation(Meter::from(200))
                ->withNumberOfActivities(2)
                ->withTotalCalories(1000)
                ->withNotes("Serial 123\nBought in Ghent")
                ->withMediaItemId(MediaItemId::fromUnprefixed('bike'))
                ->build()
                ->withActivityTypes(ActivityTypes::fromArray([ActivityType::RIDE]))
                ->withMainActivityType(ActivityType::RIDE)
                ->withMediaItem($mediaItem),
            $this->gearRepository->find($gear->getId())
        );
    }

    public function testItShouldPersistAndReadPurchasePrice(): void
    {
        $gear = GearBuilder::fromDefaults()
            ->withGearId(GearId::fromUnprefixed('1'))
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

    public function testDelete(): void
    {
        $this->gearRepository->add(GearBuilder::fromDefaults()->withGearId(GearId::fromUnprefixed('1'))->build());
        $this->gearRepository->add(GearBuilder::fromDefaults()->withGearId(GearId::fromUnprefixed('2'))->build());

        $this->gearRepository->delete($this->gearRepository->find(GearId::fromUnprefixed('1')));

        $this->assertEquals(
            [GearId::fromUnprefixed('2')],
            $this->gearRepository->findAll()->map(static fn (Gear $gear): GearId => $gear->getId()),
        );
    }

    public function testAddPersistsType(): void
    {
        $this->gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->build()
        );
        $this->gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('2'))
                ->withGearType(GearType::CUSTOM)
                ->withMediaItemId(MediaItemId::fromUnprefixed('bike'))
                ->build()
        );

        $this->assertEquals(
            [
                [
                    'gearId' => 'gear-1',
                    'createdOn' => '2023-10-10 00:00:00',
                    'name' => 'Existing gear',
                    'isRetired' => 0,
                    'type' => 'imported',
                    'mediaItemId' => null,
                    'purchasePriceAmount' => null,
                    'purchasePriceCurrency' => null,
                    'notes' => null,
                ],
                [
                    'gearId' => 'gear-2',
                    'createdOn' => '2023-10-10 00:00:00',
                    'name' => 'Existing gear',
                    'isRetired' => 0,
                    'type' => 'custom',
                    'mediaItemId' => 'mediaItem-bike',
                    'purchasePriceAmount' => null,
                    'purchasePriceCurrency' => null,
                    'notes' => null,
                ],
            ],
            $this->getConnection()->executeQuery('SELECT * FROM Gear ORDER BY gearId')->fetchAllAssociative(),
        );

        $gearWithMissingMediaItem = $this->gearRepository->find(GearId::fromUnprefixed('2'));
        $this->assertEquals(MediaItemId::fromUnprefixed('bike'), $gearWithMissingMediaItem->getMediaItemId());
        $this->assertNull($gearWithMissingMediaItem->getMediaItem());
    }

    public function testUpdate(): void
    {
        $gear = GearBuilder::fromDefaults()
            ->withGearId(GearId::fromUnprefixed('1'))
            ->withPurchasePrice(Money::EUR(150000))
            ->build();
        $this->gearRepository->add($gear);

        $this->gearRepository->update(
            $gear->withName('Updated gear')
                ->withIsRetired(true)
                ->withNotes('Some notes')
                ->withMediaItemId(MediaItemId::fromUnprefixed('bike'))
        );

        $this->assertEquals(
            [
                [
                    'gearId' => 'gear-1',
                    'createdOn' => '2023-10-10 00:00:00',
                    'name' => 'Updated gear',
                    'isRetired' => 1,
                    'type' => 'imported',
                    'mediaItemId' => 'mediaItem-bike',
                    'purchasePriceAmount' => 150000,
                    'purchasePriceCurrency' => 'EUR',
                    'notes' => 'Some notes',
                ],
            ],
            $this->getConnection()->executeQuery('SELECT * FROM Gear')->fetchAllAssociative(),
        );
    }

    public function testFindAll(): void
    {
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        $mediaItemOne = MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('one'))
            ->withPath(MediaItemPath::fromString('gear/one.jpg'))
            ->build();
        $mediaItemTwo = MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('two'))
            ->withPath(MediaItemPath::fromString('gear/two.jpg'))
            ->build();
        $this->getContainer()->get(MediaItemRepository::class)->add($mediaItemOne);
        $this->getContainer()->get(MediaItemRepository::class)->add($mediaItemTwo);

        $gearOne = GearBuilder::fromDefaults()
            ->withGearId(GearId::fromUnprefixed('1'))
            ->withDistanceInMeter(Meter::from(1230))
            ->withMediaItemId(MediaItemId::fromUnprefixed('one'))
            ->build();
        $this->gearRepository->add($gearOne);
        $gearTwo = GearBuilder::fromDefaults()
            ->withGearId(GearId::fromUnprefixed('2'))
            ->withGearType(GearType::CUSTOM)
            ->withDistanceInMeter(Meter::from(10230))
            ->withMediaItemId(MediaItemId::fromUnprefixed('two'))
            ->build();
        $this->gearRepository->add($gearTwo);
        $gearThree = GearBuilder::fromDefaults()
            ->withGearId(GearId::fromUnprefixed('3'))
            ->withDistanceInMeter(Meter::from(230))
            ->build();
        $this->gearRepository->add($gearThree);
        $gearFour = GearBuilder::fromDefaults()
            ->withGearId(GearId::fromUnprefixed('4'))
            ->withGearType(GearType::CUSTOM)
            ->withDistanceInMeter(Meter::from(100230))
            ->withIsRetired(true)
            ->build();
        $this->gearRepository->add($gearFour);

        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withDistance(Kilometer::from(10))
                ->withElevation(Meter::from(100))
                ->withMovingTimeInSeconds(3600)
                ->build(),
            []
        ));
        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withDistance(Kilometer::from(20))
                ->withElevation(Meter::from(200))
                ->withMovingTimeInSeconds(7200)
                ->build(),
            []
        ));
        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withGearId(GearId::fromUnprefixed('2'))
                ->withDistance(Kilometer::from(5))
                ->withElevation(Meter::from(50))
                ->withMovingTimeInSeconds(1800)
                ->build(),
            []
        ));
        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('4'))
                ->withGearId(GearId::fromUnprefixed('2'))
                ->withSportType(SportType::WALK)
                ->withDistance(Kilometer::from(1))
                ->withElevation(Meter::zero())
                ->withMovingTimeInSeconds(600)
                ->build(),
            []
        ));
        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('5'))
                ->withGearId(GearId::fromUnprefixed('2'))
                ->withSportType(SportType::WALK)
                ->withDistance(Kilometer::from(1))
                ->withElevation(Meter::zero())
                ->withMovingTimeInSeconds(600)
                ->build(),
            []
        ));

        $this->assertEquals(
            Gears::fromArray([
                GearBuilder::fromDefaults()
                    ->withGearId(GearId::fromUnprefixed('1'))
                    ->withDistanceInMeter(Meter::from(30000))
                    ->withMovingTime(Seconds::from(10800))
                    ->withElevation(Meter::from(300))
                    ->withNumberOfActivities(2)
                    ->withMediaItemId(MediaItemId::fromUnprefixed('one'))
                    ->build()
                    ->withActivityTypes(ActivityTypes::fromArray([ActivityType::RIDE]))
                    ->withMainActivityType(ActivityType::RIDE)
                    ->withMediaItem($mediaItemOne),
                GearBuilder::fromDefaults()
                    ->withGearId(GearId::fromUnprefixed('2'))
                    ->withGearType(GearType::CUSTOM)
                    ->withDistanceInMeter(Meter::from(7000))
                    ->withMovingTime(Seconds::from(3000))
                    ->withElevation(Meter::from(50))
                    ->withNumberOfActivities(3)
                    ->withMediaItemId(MediaItemId::fromUnprefixed('two'))
                    ->build()
                    ->withActivityTypes(ActivityTypes::fromArray([ActivityType::WALK, ActivityType::RIDE]))
                    ->withMainActivityType(ActivityType::WALK)
                    ->withMediaItem($mediaItemTwo),
                GearBuilder::fromDefaults()
                    ->withGearId(GearId::fromUnprefixed('3'))
                    ->withDistanceInMeter(Meter::zero())
                    ->build(),
                GearBuilder::fromDefaults()
                    ->withGearId(GearId::fromUnprefixed('4'))
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
                    ->withGearId(GearId::fromUnprefixed('4'))
                    ->build(),
                []
            ),
        );

        $this->gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('4'))
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
            $this->getContainer()->get(EventBus::class),
            $this->getContainer()->get(MediaItemRepository::class),
        );
    }
}
