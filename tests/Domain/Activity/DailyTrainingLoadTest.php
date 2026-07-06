<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\DailyTrainingLoad;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetric;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;

class DailyTrainingLoadTest extends ContainerTestCase
{
    private DailyTrainingLoad $dailyTrainingLoad;

    public function testCalculateWithPowerBasedData(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withAveragePower(250)
            ->withMovingTimeInSeconds(3600)
            ->withSportType(SportType::RIDE)
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

        $this->assertEquals(
            100,
            $this->dailyTrainingLoad->calculate(SerializableDateTime::fromString('2023-10-10')),
        );
    }

    public function testCalculateWhenFtpNotFound(): void
    {
        // Remove the FTP history so the power-based calculation falls back to heart rate.
        $this->getContainer()->get(SettingsRepository::class)->save(SettingsGroup::GENERAL, [
            'athlete' => [
                'birthday' => '1989-08-14',
                'firstName' => 'Robin',
                'lastName' => 'Ingelbrecht',
                'maxHeartRateFormula' => 'fox',
            ],
        ]);

        $activity = ActivityBuilder::fromDefaults()
            ->withAveragePower(250)
            ->withAverageHeartRate(171)
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

        $this->assertEquals(
            105,
            $this->dailyTrainingLoad->calculate(SerializableDateTime::fromString('2023-10-10')),
        );
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

        $this->assertEquals(
            105,
            $this->dailyTrainingLoad->calculate(SerializableDateTime::fromString('2023-10-10')),
        );
    }

    public function testCalculateShouldBeZero(): void
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
            $this->dailyTrainingLoad->calculate(SerializableDateTime::fromString('2023-10-10')),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->dailyTrainingLoad = $this->getContainer()->get(DailyTrainingLoad::class);
    }
}
