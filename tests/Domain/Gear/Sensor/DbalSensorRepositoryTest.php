<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Sensor;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\Sensor\ConnectedSensor;
use App\Domain\Gear\Sensor\ConnectedSensors;
use App\Domain\Gear\Sensor\Sensor;
use App\Domain\Gear\Sensor\SensorRepository;
use App\Domain\Gear\Sensor\SensorType;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class DbalSensorRepositoryTest extends ContainerTestCase
{
    private SensorRepository $sensorRepository;

    public function testFindAllWithoutSensors(): void
    {
        $this->assertTrue($this->sensorRepository->findAll()->isEmpty());
    }

    public function testFindAllIgnoresActivitiesThatReportedNoSensors(): void
    {
        $this->addActivity('1', null);
        $this->addActivity('2', ConnectedSensors::empty());

        $this->assertTrue($this->sensorRepository->findAll()->isEmpty());
    }

    public function testFindAllCountsEveryActivityASensorWasPairedWith(): void
    {
        $varia = ConnectedSensor::create(1, 3592, 3485049140, 'Garmin Varia', SensorType::BIKE_RADAR, SensorType::BIKE_LIGHT);
        $powerMeter = ConnectedSensor::create(1, 3578, 3485828557, 'Garmin Rally 200', SensorType::POWER_METER);

        $this->addActivity('1', ConnectedSensors::fromSensors($varia, $powerMeter));
        $this->addActivity('2', ConnectedSensors::fromSensors($varia, $powerMeter));
        $this->addActivity('3', ConnectedSensors::fromSensors($varia));

        $sensors = $this->sensorRepository->findAll();

        $this->assertCount(2, $sensors);
        $this->assertSame(
            ['Garmin Varia', 'Garmin Rally 200'],
            array_map(static fn (Sensor $sensor): ?string => $sensor->getName(), $sensors->toArray())
        );
        $this->assertSame([3, 2], array_map(static fn (Sensor $sensor): int => $sensor->getActivityCount(), $sensors->toArray()));
    }

    public function testFindAllMergesTheTypesOneSensorWasSeenIn(): void
    {
        $this->addActivity('1', ConnectedSensors::fromSensors(
            ConnectedSensor::create(1, 3592, 3485049140, 'Garmin Varia', SensorType::BIKE_RADAR),
        ));
        $this->addActivity('2', ConnectedSensors::fromSensors(
            ConnectedSensor::create(1, 3592, 3485049140, 'Garmin Varia', SensorType::BIKE_RADAR, SensorType::BIKE_LIGHT),
        ));

        $sensors = $this->sensorRepository->findAll();

        $this->assertCount(1, $sensors);
        $this->assertSame([SensorType::BIKE_RADAR, SensorType::BIKE_LIGHT], $sensors->toArray()[0]->getSensorTypes());
        $this->assertSame([SensorType::BIKE_RADAR, SensorType::BIKE_LIGHT], $sensors->getSensorTypes());
    }

    public function testGetSensorTypesReturnsEveryKindOwnedOnlyOnce(): void
    {
        $this->addActivity('1', ConnectedSensors::fromSensors(
            ConnectedSensor::create(1, 3578, 3485828557, 'Garmin Rally 200', SensorType::POWER_METER),
        ));
        $this->addActivity('2', ConnectedSensors::fromSensors(
            ConnectedSensor::create(263, 1, 42, 'Favero Assioma', SensorType::POWER_METER),
            ConnectedSensor::create(123, 3, 550082112, null, SensorType::HEART_RATE_MONITOR),
        ));

        $this->assertSame(
            [SensorType::HEART_RATE_MONITOR, SensorType::POWER_METER],
            $this->sensorRepository->findAll()->getSensorTypes()
        );
    }

    private function addActivity(string $activityId, ?ConnectedSensors $connectedSensors): void
    {
        $builder = ActivityBuilder::fromDefaults()->withActivityId(ActivityId::fromUnprefixed($activityId));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ($connectedSensors instanceof ConnectedSensors ? $builder->withConnectedSensors($connectedSensors) : $builder)->build(),
            []
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->sensorRepository = $this->getContainer()->get(SensorRepository::class);
    }
}
