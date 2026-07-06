<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\ActivityIntensity;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\CouldNotDetermineActivityIntensity;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetric;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;

class ActivityIntensityTest extends ContainerTestCase
{
    private ActivityIntensity $activityIntensity;

    public function testCalculateWithPower(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withAverageHeartRate(250)
            ->withMovingTimeInSeconds(3600)
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
            ->build();

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            $activity,
            []
        ));
        $this->getContainer()->get(ActivityStreamMetricRepository::class)->add(ActivityStreamMetric::create(
            activityId: $activity->getId(),
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::NORMALIZED_POWER,
            data: [250],
        ));

        $this->assertEmpty(ActivityIntensity::$cachedIntensities);
        $this->assertEquals(
            100,
            $this->activityIntensity->calculate($activity->getId()),
        );
        $this->assertArrayHasKey(
            (string) $activity->getId(),
            ActivityIntensity::$cachedIntensities
        );
        $this->assertEquals(
            100,
            $this->activityIntensity->calculatePowerBased($activity->getId()),
        );
    }

    public function testCalculateWithPowerWhenEmptyNormalizedPower(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withAverageHeartRate(250)
            ->withMovingTimeInSeconds(3600)
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
            ->build();

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            $activity,
            []
        ));

        $this->expectExceptionObject(new CouldNotDetermineActivityIntensity('Activity has no normalized power'));
        $this->activityIntensity->calculatePowerBased($activity->getId());
    }

    public function testCalculateWithPowerWhenActivityIsNotARide(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withSportType(SportType::RUN)
            ->build();

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            $activity,
            []
        ));

        $this->expectExceptionObject(new CouldNotDetermineActivityIntensity('Activity is not a ride'));
        $this->activityIntensity->calculatePowerBased($activity->getId());
    }

    public function testCalculateWithPowerWhenFtpNotFound(): void
    {
        // Remove the FTP history so the power-based calculation cannot find an FTP.
        $this->getContainer()->get(SettingsRepository::class)->save(SettingsGroup::GENERAL, [
            'athlete' => [
                'birthday' => '1989-08-14',
                'firstName' => 'Robin',
                'lastName' => 'Ingelbrecht',
                'maxHeartRateFormula' => 'fox',
            ],
        ]);

        $activity = ActivityBuilder::fromDefaults()
            ->withAverageHeartRate(250)
            ->withMovingTimeInSeconds(3600)
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
            ->build();

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            $activity,
            []
        ));
        $this->getContainer()->get(ActivityStreamMetricRepository::class)->add(ActivityStreamMetric::create(
            activityId: $activity->getId(),
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::NORMALIZED_POWER,
            data: [250],
        ));

        $this->expectExceptionObject(new CouldNotDetermineActivityIntensity('Ftp not found'));
        $this->activityIntensity->calculatePowerBased($activity->getId());
    }

    public function testCalculateWithHeartRate(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withAverageHeartRate(171)
            ->withMovingTimeInSeconds(3600)
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
            ->build();

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            $activity,
            []
        ));

        $this->assertEmpty(ActivityIntensity::$cachedIntensities);
        $this->assertEquals(
            87,
            $this->activityIntensity->calculateHeartRateBased($activity->getId()),
        );
        $this->assertArrayHasKey(
            (string) $activity->getId(),
            ActivityIntensity::$cachedIntensities
        );
        $this->assertEquals(
            87,
            $this->activityIntensity->calculateHeartRateBased($activity->getId()),
        );
    }

    public function testCalculateWithoutAnyData(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withMovingTimeInSeconds(3600)
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
            ->withAverageHeartRate(0)
            ->build();

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            $activity,
            []
        ));

        $this->assertEquals(
            0,
            $this->activityIntensity->calculate($activity->getId()),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityIntensity = $this->getContainer()->get(ActivityIntensity::class);
    }
}
