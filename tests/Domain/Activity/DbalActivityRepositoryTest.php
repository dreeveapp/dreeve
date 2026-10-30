<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityName;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\DbalActivityRepository;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearId;
use App\Domain\Gear\Sensor\ConnectedSensor;
use App\Domain\Gear\Sensor\ConnectedSensors;
use App\Domain\Gear\Sensor\SensorType;
use App\Domain\Integration\Weather\OpenMeteo\Weather;
use App\Infrastructure\Eventing\EventBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Velocity\MetersPerSecond;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class DbalActivityRepositoryTest extends ContainerTestCase
{
    private ActivityRepository $activityRepository;

    public function testFind(): void
    {
        $activityOne = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1))
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 14:00:34'))
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState(
            $activityOne,
            ['raw' => 'data']
        ));

        $this->assertEquals(
            $activityOne,
            $this->activityRepository->find($activityOne->getId())
        );

        $activityTwo = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(2))
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 14:00:34'))
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState(
            $activityTwo,
            ['raw' => 'data']
        ));

        $this->assertEquals(
            $activityTwo,
            $this->activityRepository->find($activityTwo->getId())
        );
    }

    public function testFindWithEmptyNameItShouldFallBackToGeneratedName(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1))
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 14:00:34'))
            ->withSportType(SportType::RIDE)
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState(
            $activity,
            ['raw' => 'data']
        ));

        // Legacy activities can contain empty titles...
        $this->getConnection()->executeStatement(
            'UPDATE Activity SET name = :name WHERE activityId = :activityId',
            [
                'name' => '   ',
                'activityId' => $activity->getId(),
            ]
        );

        $persisted = $this->activityRepository->find($activity->getId());

        $this->assertEquals(
            'Afternoon Ride',
            $persisted->getName()
        );
        $this->assertEquals(
            ActivityName::from(
                SerializableDateTime::fromString('2023-10-10 14:00:34'),
                SportType::RIDE
            ),
            ActivityName::fromString($persisted->getName())
        );
    }

    public function testItShouldThrowWhenNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('Activity "activity-1" not found'));
        $this->activityRepository->find(ActivityId::fromUnprefixed(1));
    }

    public function testFindWithRawDataItShouldThrowWhenNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('Activity "activity-1" not found'));
        $this->activityRepository->findWithRawData(ActivityId::fromUnprefixed(1));
    }

    public function testAddAndFindWithRawData(): void
    {
        $activityWithRawData = ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->build(),
            ['raw' => 'data']
        );

        $this->activityRepository->add($activityWithRawData);

        $persisted = $this->activityRepository->findWithRawData($activityWithRawData->getActivity()->getId());
        $this->assertEquals(
            $activityWithRawData,
            $persisted,
        );
    }

    #[DataProvider('provideConnectedSensors')]
    public function testAddAndFindPreservesTheDifferenceBetweenUnknownAndNoSensors(?ConnectedSensors $connectedSensors): void
    {
        $builder = ActivityBuilder::fromDefaults();
        $activity = ($connectedSensors instanceof ConnectedSensors ? $builder->withConnectedSensors($connectedSensors) : $builder)->build();

        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));

        $this->assertEquals(
            $connectedSensors,
            $this->activityRepository->find($activity->getId())->getConnectedSensors()
        );
    }

    public static function provideConnectedSensors(): iterable
    {
        yield 'unknown' => [null];
        yield 'known, but none connected' => [ConnectedSensors::fromSensors()];
        yield 'known' => [ConnectedSensors::fromSensors(
            ConnectedSensor::create(1, 3592, 3485049140, 'Garmin Varia', SensorType::BIKE_RADAR, SensorType::BIKE_LIGHT),
        )];
    }

    public function testAddAndFindWithRawDataFromRawData(): void
    {
        $rawData = Json::decode((string) file_get_contents(__DIR__.'/fixtures/raw-strava-run-activity.json'));

        $activityWithRawData = ActivityWithRawData::fromState(
            Activity::createFromRawStravaData($rawData),
            $rawData
        );

        $this->activityRepository->add($activityWithRawData);

        $persisted = $this->activityRepository->findWithRawData($activityWithRawData->getActivity()->getId());
        $this->assertEquals(
            $activityWithRawData,
            $persisted,
        );
    }

    public function testUpdate(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();
        $activityWithRawData = ActivityWithRawData::fromState(
            $activity,
            ['raw' => 'data']
        );

        $this->activityRepository->add($activityWithRawData);

        $activity = $activity
            ->withName(ActivityName::fromString('Updated name'))
            ->withDescription('Updated description')
            ->withDeviceName('Updated device')
            ->withSportType(SportType::BADMINTON)
            ->withDistance(Kilometer::from(9.99))
            ->withAverageSpeed(MetersPerSecond::from(19.99)->toKmPerHour())
            ->withMaxSpeed(MetersPerSecond::from(99.99)->toKmPerHour())
            ->withMovingTimeInSeconds(999)
            ->withElevation(Meter::from(9999))
            ->withCalories(1234)
            ->withKilojoules(4321)
            ->withAverageHeartRate(142)
            ->withMaxHeartRate(175)
            ->withPolyline('updated polyline')
            ->withStartingCoordinate(Coordinate::createFromLatAndLng(
                latitude: Latitude::fromString('20'),
                longitude: Longitude::fromString('20'),
            ))
            ->withGear(GearId::fromUnprefixed('updated'))
            ->withRouteGeography(RouteGeography::create(['state' => 'updated location']))
            ->withWeather(Weather::fromState(['temperature_2m' => 18.5]))
            ->withCommute(true)
            ->withGroupActivity(true);

        $this->activityRepository->update(ActivityWithRawData::fromState(
            activity: $activity,
            rawData: ['updated' => 'lol']
        ));

        $persisted = $this->activityRepository->findWithRawData($activity->getId());
        $persistedActivity = $persisted->getActivity();
        $this->assertEquals(
            ['updated' => 'lol'],
            $persisted->getRawData()
        );
        $this->assertSame(
            1234,
            $persistedActivity->getCalories()
        );
        $this->assertSame(
            4321,
            $persistedActivity->getKilojoules()
        );
        $this->assertSame(
            142,
            $persistedActivity->getAverageHeartRate()
        );
        $this->assertSame(
            175,
            $persistedActivity->getMaxHeartRate()
        );
        $this->assertSame(
            18.5,
            $persistedActivity->getWeather()?->getTemperatureInCelsius()->toFloat()
        );
        $this->assertEquals(
            'Updated name',
            $persistedActivity->getName()
        );
        $this->assertEquals(
            'Updated description',
            $persistedActivity->getDescription()
        );
        $this->assertEquals(
            'Updated device',
            $persistedActivity->getDeviceName()
        );
        $this->assertEquals(
            Kilometer::from(9.99),
            $persistedActivity->getDistance()
        );
        $this->assertEquals(
            MetersPerSecond::from(19.99)->toKmPerHour(),
            $persistedActivity->getAverageSpeed()
        );
        $this->assertEquals(
            MetersPerSecond::from(99.99)->toKmPerHour(),
            $persistedActivity->getMaxSpeed()
        );
        $this->assertEquals(
            999,
            $persistedActivity->getMovingTimeInSeconds()
        );
        $this->assertEquals(
            Meter::from(9999),
            $persistedActivity->getElevation()
        );
        $this->assertEquals(
            'updated polyline',
            (string) $persistedActivity->getEncodedPolyline()
        );
        $this->assertEquals(
            GearId::fromUnprefixed('updated'),
            $persistedActivity->getGearId()
        );
        $this->assertEquals(
            RouteGeography::create(['state' => 'updated location']),
            $persistedActivity->getRouteGeography()
        );
        $this->assertTrue($persistedActivity->isCommute());
        $this->assertTrue($persistedActivity->isGroupActivity());
        $this->assertEquals(
            Coordinate::createFromLatAndLng(
                latitude: Latitude::fromString('20'),
                longitude: Longitude::fromString('20'),
            ),
            $persistedActivity->getStartingCoordinate(),
        );
        $this->assertEquals(
            SportType::BADMINTON,
            $persistedActivity->getSportType()
        );
    }

    public function testFindByDateRange(): void
    {
        foreach ([
            1 => '2023-09-30 23:59:59',
            2 => '2023-10-01 00:00:00',
            3 => '2023-10-15 12:00:00',
            4 => '2023-10-31 23:59:59',
            5 => '2023-11-01 00:00:00',
        ] as $activityId => $startDateTime) {
            $this->activityRepository->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($activityId))
                    ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                    ->build(),
                ['raw' => 'data']
            ));
        }

        $activities = $this->activityRepository->findByDateRange(
            from: SerializableDateTime::fromString('2023-10-01 00:00:00'),
            till: SerializableDateTime::fromString('2023-11-01 00:00:00'),
        );

        $this->assertEquals(
            [
                ActivityId::fromUnprefixed(4),
                ActivityId::fromUnprefixed(3),
                ActivityId::fromUnprefixed(2),
            ],
            $activities->map(fn (Activity $activity): ActivityId => $activity->getId())
        );
    }

    public function testFindByDateRangeItShouldReturnEmptyWhenNothingIsInRange(): void
    {
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-15 12:00:00'))
                ->build(),
            ['raw' => 'data']
        ));

        $this->assertTrue($this->activityRepository->findByDateRange(
            from: SerializableDateTime::fromString('2024-01-01 00:00:00'),
            till: SerializableDateTime::fromString('2024-02-01 00:00:00'),
        )->isEmpty());
    }

    public function testFindMostRecent(): void
    {
        foreach ([
            1 => '2023-10-01 00:00:00',
            2 => '2023-10-02 00:00:00',
            3 => '2023-10-03 00:00:00',
        ] as $activityId => $startDateTime) {
            $this->activityRepository->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($activityId))
                    ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                    ->build(),
                ['raw' => 'data']
            ));
        }

        $this->assertEquals(
            [
                ActivityId::fromUnprefixed(3),
                ActivityId::fromUnprefixed(2),
            ],
            $this->activityRepository->findMostRecent(2)->map(fn (Activity $activity): ActivityId => $activity->getId())
        );
    }

    public function testFindMostRecentRestrictedToSportTypes(): void
    {
        foreach ([
            1 => SportType::RIDE,
            2 => SportType::RUN,
            3 => SportType::RIDE,
        ] as $activityId => $sportType) {
            $this->activityRepository->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($activityId))
                    ->withStartDateTime(SerializableDateTime::fromString(sprintf('2023-10-0%d 00:00:00', $activityId)))
                    ->withSportType($sportType)
                    ->build(),
                ['raw' => 'data']
            ));
        }

        $this->assertEquals(
            [ActivityId::fromUnprefixed(2)],
            $this->activityRepository->findMostRecent(
                limit: 5,
                restrictToSportTypes: SportTypes::fromArray([SportType::RUN]),
            )->map(fn (Activity $activity): ActivityId => $activity->getId())
        );
    }

    public function testFindMostRecentOnlyActivitiesWithARoute(): void
    {
        foreach ([
            1 => 'a-polyline',
            2 => null,
            3 => '',
            4 => 'another-polyline',
        ] as $activityId => $polyline) {
            $this->activityRepository->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($activityId))
                    ->withStartDateTime(SerializableDateTime::fromString(sprintf('2023-10-0%d 00:00:00', $activityId)))
                    ->withPolyline($polyline)
                    ->build(),
                ['raw' => 'data']
            ));
        }

        $this->assertEquals(
            [
                ActivityId::fromUnprefixed(4),
                ActivityId::fromUnprefixed(1),
            ],
            $this->activityRepository->findMostRecent(
                limit: 10,
                onlyActivitiesWithARoute: true,
            )->map(fn (Activity $activity): ActivityId => $activity->getId())
        );
    }

    public function testDelete(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();
        $this->activityRepository->add(ActivityWithRawData::fromState(
            $activity,
            ['raw' => 'data']
        ));

        $activity->delete();
        $this->activityRepository->delete($activity);

        $this->expectExceptionObject(new EntityNotFound(sprintf('Activity "%s" not found', $activity->getId())));
        $this->activityRepository->find($activity->getId());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityRepository = new DbalActivityRepository(
            $this->getConnection(),
            $this->getContainer()->get(EventBus::class),
        );
    }
}
