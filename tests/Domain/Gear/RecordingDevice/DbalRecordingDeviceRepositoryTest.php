<?php

namespace App\Tests\Domain\Gear\RecordingDevice;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\RecordingDevice\DbalRecordingDeviceRepository;
use App\Domain\Gear\RecordingDevice\RecordingDevice;
use App\Domain\Gear\RecordingDevice\RecordingDeviceId;
use App\Domain\Gear\RecordingDevice\RecordingDeviceRepository;
use App\Domain\Gear\RecordingDevice\RecordingDevices;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use Money\Currency;
use Money\Money;

class DbalRecordingDeviceRepositoryTest extends ContainerTestCase
{
    private RecordingDeviceRepository $recordingDeviceRepository;

    public function testFindAll(): void
    {
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withDeviceName('Garmin Edge 530')
                ->withDistance(Kilometer::from(10))
                ->withElevation(Meter::from(100))
                ->withMovingTimeInSeconds(3600)
                ->build(),
            []
        ));

        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withDeviceName('Garmin Edge 530')
                ->withDistance(Kilometer::from(20))
                ->withElevation(Meter::from(200))
                ->withMovingTimeInSeconds(7200)
                ->build(),
            []
        ));

        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withDeviceName('Garmin Forerunner 945')
                ->withDistance(Kilometer::from(5))
                ->withElevation(Meter::from(50))
                ->withMovingTimeInSeconds(1800)
                ->build(),
            []
        ));

        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('4'))
                ->withoutDeviceName()
                ->build(),
            []
        ));

        $devices = $this->recordingDeviceRepository->findAll();

        $this->assertEquals(
            RecordingDevices::fromArray([
                RecordingDevice::fromState(
                    id: RecordingDeviceId::fromName('Garmin Edge 530'),
                    name: 'Garmin Edge 530',
                    timeTracked: Seconds::from(10800),
                    distanceTracked: Meter::from(30000)->toKilometer(),
                    elevationTracked: Meter::from(300),
                    activityCount: 2,
                    purchasePrice: null,
                ),
                RecordingDevice::fromState(
                    id: RecordingDeviceId::fromName('Garmin Forerunner 945'),
                    name: 'Garmin Forerunner 945',
                    timeTracked: Seconds::from(1800),
                    distanceTracked: Meter::from(5000)->toKilometer(),
                    elevationTracked: Meter::from(50),
                    activityCount: 1,
                    purchasePrice: null,
                ),
            ]),
            $devices,
        );
    }

    public function testFindAllReadsPurchasePriceFromTable(): void
    {
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withDeviceName('Garmin Edge 530')
                ->withDistance(Kilometer::from(10))
                ->withElevation(Meter::from(100))
                ->withMovingTimeInSeconds(3600)
                ->build(),
            []
        ));

        $this->getConnection()->executeStatement(
            "INSERT INTO RecordingDevice (id, name, purchasePriceAmount, purchasePriceCurrency)
             VALUES ('recordingDevice-garmin-edge-530', 'Garmin Edge 530', 29950, 'EUR')"
        );

        $this->assertEquals(
            RecordingDevices::fromArray([
                RecordingDevice::fromState(
                    id: RecordingDeviceId::fromName('Garmin Edge 530'),
                    name: 'Garmin Edge 530',
                    timeTracked: Seconds::from(3600),
                    distanceTracked: Meter::from(10000)->toKilometer(),
                    elevationTracked: Meter::from(100),
                    activityCount: 1,
                    purchasePrice: new Money(29950, new Currency('EUR')),
                ),
            ]),
            $this->recordingDeviceRepository->findAll(),
        );
    }

    public function testFindAllWithNoActivities(): void
    {
        $this->assertEquals(
            RecordingDevices::empty(),
            $this->recordingDeviceRepository->findAll(),
        );
    }

    public function testFindAllExcludesActivitiesWithoutDeviceName(): void
    {
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withoutDeviceName()
                ->build(),
            []
        ));

        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withoutDeviceName()
                ->build(),
            []
        ));

        $this->assertEquals(
            RecordingDevices::empty(),
            $this->recordingDeviceRepository->findAll(),
        );
    }

    public function testFind(): void
    {
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withDeviceName('Garmin Edge 530')
                ->withDistance(Kilometer::from(10))
                ->withElevation(Meter::from(100))
                ->withMovingTimeInSeconds(3600)
                ->build(),
            []
        ));

        $this->getConnection()->executeStatement(
            "INSERT INTO RecordingDevice (id, name, purchasePriceAmount, purchasePriceCurrency)
             VALUES ('recordingDevice-garmin-edge-530', 'Garmin Edge 530', 29950, 'EUR')"
        );

        $this->assertEquals(
            RecordingDevice::fromState(
                id: RecordingDeviceId::fromName('Garmin Edge 530'),
                name: 'Garmin Edge 530',
                timeTracked: Seconds::from(3600),
                distanceTracked: Meter::from(10000)->toKilometer(),
                elevationTracked: Meter::from(100),
                activityCount: 1,
                purchasePrice: new Money(29950, new Currency('EUR')),
            ),
            $this->recordingDeviceRepository->find(RecordingDeviceId::fromName('Garmin Edge 530')),
        );
    }

    public function testFindThrowsWhenNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('RecordingDevice "recordingDevice-garmin-edge-530" not found'));

        $this->recordingDeviceRepository->find(RecordingDeviceId::fromName('Garmin Edge 530'));
    }

    public function testSave(): void
    {
        $this->recordingDeviceRepository->save(
            RecordingDevice::create(
                name: 'Garmin Edge 530',
                purchasePrice: new Money(29950, new Currency('EUR')),
            )
        );

        $this->assertEquals(
            [[
                'id' => 'recordingDevice-garmin-edge-530',
                'name' => 'Garmin Edge 530',
                'purchasePriceAmount' => 29950,
                'purchasePriceCurrency' => 'EUR',
            ]],
            $this->getConnection()->fetchAllAssociative(
                'SELECT id, name, purchasePriceAmount, purchasePriceCurrency FROM RecordingDevice'
            ),
        );
    }

    public function testSaveWithoutPurchasePrice(): void
    {
        $this->recordingDeviceRepository->save(
            RecordingDevice::create(
                name: 'Garmin Edge 530',
                purchasePrice: null,
            )
        );

        $this->assertEquals(
            [[
                'id' => 'recordingDevice-garmin-edge-530',
                'name' => 'Garmin Edge 530',
                'purchasePriceAmount' => null,
                'purchasePriceCurrency' => null,
            ]],
            $this->getConnection()->fetchAllAssociative(
                'SELECT id, name, purchasePriceAmount, purchasePriceCurrency FROM RecordingDevice'
            ),
        );
    }

    public function testSaveUpdatesExistingDeviceOnConflict(): void
    {
        $this->recordingDeviceRepository->save(
            RecordingDevice::create(
                name: 'Garmin Edge 530',
                purchasePrice: new Money(29950, new Currency('EUR')),
            )
        );

        $this->recordingDeviceRepository->save(
            RecordingDevice::create(
                name: 'Garmin Edge 530',
                purchasePrice: new Money(19950, new Currency('USD')),
            )
        );

        $this->assertEquals(
            [[
                'id' => 'recordingDevice-garmin-edge-530',
                'name' => 'Garmin Edge 530',
                'purchasePriceAmount' => 19950,
                'purchasePriceCurrency' => 'USD',
            ]],
            $this->getConnection()->fetchAllAssociative(
                'SELECT id, name, purchasePriceAmount, purchasePriceCurrency FROM RecordingDevice'
            ),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->recordingDeviceRepository = new DbalRecordingDeviceRepository(
            $this->getConnection(),
        );
    }
}
