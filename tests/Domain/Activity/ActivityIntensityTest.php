<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityIntensity;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\CouldNotDetermineActivityIntensity;
use App\Domain\Activity\EnrichedActivity;
use App\Domain\Activity\EnrichedActivityRepository;
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
        $enrichedActivity = $this->persist(
            ActivityBuilder::fromDefaults()
                ->withAverageHeartRate(250)
                ->withMovingTimeInSeconds(3600)
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
                ->build(),
            normalizedPower: 250,
        );

        $this->assertEquals(
            100,
            $this->activityIntensity->calculate($enrichedActivity),
        );
        $this->assertEquals(
            100,
            $this->activityIntensity->calculatePowerBased($enrichedActivity),
        );
    }

    public function testCalculateWithPowerWhenEmptyNormalizedPower(): void
    {
        $enrichedActivity = $this->persist(
            ActivityBuilder::fromDefaults()
                ->withAverageHeartRate(250)
                ->withMovingTimeInSeconds(3600)
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
                ->build()
        );

        $this->expectExceptionObject(new CouldNotDetermineActivityIntensity('Activity has no normalized power'));
        $this->activityIntensity->calculatePowerBased($enrichedActivity);
    }

    public function testCalculateWithPowerWhenActivityIsNotARide(): void
    {
        $enrichedActivity = $this->persist(
            ActivityBuilder::fromDefaults()
                ->withSportType(SportType::RUN)
                ->build()
        );

        $this->expectExceptionObject(new CouldNotDetermineActivityIntensity('Activity is not a ride'));
        $this->activityIntensity->calculatePowerBased($enrichedActivity);
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

        $enrichedActivity = $this->persist(
            ActivityBuilder::fromDefaults()
                ->withAverageHeartRate(250)
                ->withMovingTimeInSeconds(3600)
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
                ->build(),
            normalizedPower: 250,
        );

        $this->expectExceptionObject(new CouldNotDetermineActivityIntensity('Ftp not found'));
        $this->activityIntensity->calculatePowerBased($enrichedActivity);
    }

    public function testCalculateWithHeartRate(): void
    {
        $enrichedActivity = $this->persist(
            ActivityBuilder::fromDefaults()
                ->withAverageHeartRate(171)
                ->withMovingTimeInSeconds(3600)
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
                ->build()
        );

        $this->assertEquals(
            87,
            $this->activityIntensity->calculateHeartRateBased($enrichedActivity),
        );
    }

    public function testCalculateWithoutAnyData(): void
    {
        $enrichedActivity = $this->persist(
            ActivityBuilder::fromDefaults()
                ->withMovingTimeInSeconds(3600)
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
                ->withAverageHeartRate(0)
                ->build()
        );

        $this->assertEquals(
            0,
            $this->activityIntensity->calculate($enrichedActivity),
        );
    }

    private function persist(Activity $activity, ?int $normalizedPower = null): EnrichedActivity
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            $activity,
            []
        ));

        if (null !== $normalizedPower) {
            $this->getContainer()->get(ActivityStreamMetricRepository::class)->add(ActivityStreamMetric::create(
                activityId: $activity->getId(),
                streamType: StreamType::WATTS,
                metricType: ActivityStreamMetricType::NORMALIZED_POWER,
                data: [$normalizedPower],
            ));
        }

        return $this->getContainer()->get(EnrichedActivityRepository::class)->find($activity->getId());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityIntensity = $this->getContainer()->get(ActivityIntensity::class);
    }
}
