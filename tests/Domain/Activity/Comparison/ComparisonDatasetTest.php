<?php

namespace App\Tests\Domain\Activity\Comparison;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Comparison\ComparedActivities;
use App\Domain\Activity\Comparison\ComparisonDataset;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\EnrichedActivityBuilder;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Contracts\Translation\TranslatorInterface;

class ComparisonDatasetTest extends ContainerTestCase
{
    use MatchesSnapshots;

    public function testBuild(): void
    {
        $comparedActivities = ComparedActivities::fromEnrichedActivities([
            EnrichedActivityBuilder::fromDefaults()
                ->withActivity(
                    ActivityBuilder::fromDefaults()
                        ->withActivityId(ActivityId::fromUnprefixed('first'))
                        ->withName('first')
                        ->withStartDateTime(SerializableDateTime::fromString('2023-06-01 07:30:00'))
                        ->withMovingTimeInSeconds(3600)
                        ->withAverageSpeed(KmPerHour::from(30))
                        ->withElevation(Meter::from(120))
                        ->withCalories(800)
                        ->withKilojoules(720)
                        ->withAveragePower(200)
                        ->withAverageHeartRate(150)
                        ->build()
                )
                ->withNormalizedPower(215)
                ->build(),
            EnrichedActivityBuilder::fromDefaults()
                ->withActivity(
                    ActivityBuilder::fromDefaults()
                        ->withActivityId(ActivityId::fromUnprefixed('faster'))
                        ->withName('faster')
                        ->withStartDateTime(SerializableDateTime::fromString('2023-09-12 07:30:00'))
                        ->withMovingTimeInSeconds(3300)
                        ->withAverageSpeed(KmPerHour::from(30))
                        ->withElevation(Meter::from(120))
                        ->withCalories(800)
                        ->withKilojoules(720)
                        ->withAveragePower(225)
                        ->withAverageHeartRate(148)
                        ->build()
                )
                ->withNormalizedPower(240)
                ->build(),
            EnrichedActivityBuilder::fromDefaults()->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('without-power'))
                    ->withName('without-power')
                    ->withStartDateTime(SerializableDateTime::fromString('2024-01-20 07:30:00'))
                    ->withMovingTimeInSeconds(3900)
                    ->withAverageSpeed(KmPerHour::from(30))
                    ->withElevation(Meter::from(120))
                    ->withCalories(800)
                    ->build()
            )->build(),
        ]);

        $this->assertMatchesJsonSnapshot(ComparisonDataset::create(
            comparedActivities: $comparedActivities,
            unitSystem: UnitSystem::METRIC,
            translator: $this->getContainer()->get(TranslatorInterface::class),
        )->build());
    }

    public function testBuildForTheImperialUnitSystem(): void
    {
        $comparedActivities = ComparedActivities::fromEnrichedActivities([
            EnrichedActivityBuilder::fromDefaults()
                ->withActivity(
                    ActivityBuilder::fromDefaults()
                        ->withActivityId(ActivityId::fromUnprefixed('first'))
                        ->withName('first')
                        ->withStartDateTime(SerializableDateTime::fromString('2023-06-01 07:30:00'))
                        ->withMovingTimeInSeconds(3600)
                        ->withAverageSpeed(KmPerHour::from(30))
                        ->withElevation(Meter::from(120))
                        ->withCalories(800)
                        ->withKilojoules(720)
                        ->withAveragePower(200)
                        ->withAverageHeartRate(150)
                        ->build()
                )
                ->withNormalizedPower(215)
                ->build(),
            EnrichedActivityBuilder::fromDefaults()
                ->withActivity(
                    ActivityBuilder::fromDefaults()
                        ->withActivityId(ActivityId::fromUnprefixed('faster'))
                        ->withName('faster')
                        ->withStartDateTime(SerializableDateTime::fromString('2023-09-12 07:30:00'))
                        ->withMovingTimeInSeconds(3300)
                        ->withAverageSpeed(KmPerHour::from(30))
                        ->withElevation(Meter::from(120))
                        ->withCalories(800)
                        ->withKilojoules(720)
                        ->withAveragePower(225)
                        ->withAverageHeartRate(148)
                        ->build()
                )
                ->withNormalizedPower(240)
                ->build(),
            EnrichedActivityBuilder::fromDefaults()->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('without-power'))
                    ->withName('without-power')
                    ->withStartDateTime(SerializableDateTime::fromString('2024-01-20 07:30:00'))
                    ->withMovingTimeInSeconds(3900)
                    ->withAverageSpeed(KmPerHour::from(30))
                    ->withElevation(Meter::from(120))
                    ->withCalories(800)
                    ->build()
            )->build(),
        ]);

        $this->assertMatchesJsonSnapshot(ComparisonDataset::create(
            comparedActivities: $comparedActivities,
            unitSystem: UnitSystem::IMPERIAL,
            translator: $this->getContainer()->get(TranslatorInterface::class),
        )->build());
    }

    public function testBuildForASetWithoutPowerOrHeartRate(): void
    {
        $comparedActivities = ComparedActivities::fromEnrichedActivities([
            EnrichedActivityBuilder::fromDefaults()->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('one'))
                    ->withStartDateTime(SerializableDateTime::fromString('2023-01-01 07:30:00'))
                    ->build()
            )->build(),
            EnrichedActivityBuilder::fromDefaults()->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('two'))
                    ->withStartDateTime(SerializableDateTime::fromString('2023-02-01 07:30:00'))
                    ->build()
            )->build(),
        ]);

        $dataset = ComparisonDataset::create(
            comparedActivities: $comparedActivities,
            unitSystem: UnitSystem::METRIC,
            translator: $this->getContainer()->get(TranslatorInterface::class),
        )->build();

        $unavailable = array_values(array_map(
            fn (array $metric): string => $metric['key'],
            array_filter($dataset['metrics'], fn (array $metric): bool => !$metric['available'])
        ));

        $this->assertEquals(
            ['normalizedPower', 'averagePower', 'averageHeartRate', 'kilojoules', 'speedToPowerRatio', 'efficiencyFactor'],
            $unavailable
        );
        $this->assertEquals(['primary' => 'movingTime', 'secondary' => null], $dataset['defaults']);
    }

    public function testBuildFallsBackToAnAvailableSecondaryMetric(): void
    {
        $comparedActivities = ComparedActivities::fromEnrichedActivities([
            EnrichedActivityBuilder::fromDefaults()->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('one'))
                    ->withAverageHeartRate(140)
                    ->build()
            )->build(),
        ]);

        $dataset = ComparisonDataset::create(
            comparedActivities: $comparedActivities,
            unitSystem: UnitSystem::METRIC,
            translator: $this->getContainer()->get(TranslatorInterface::class),
        )->build();

        $this->assertEquals(['primary' => 'movingTime', 'secondary' => 'averageHeartRate'], $dataset['defaults']);
    }

    public function testBuildForAnEmptySet(): void
    {
        $dataset = ComparisonDataset::create(
            comparedActivities: ComparedActivities::empty(),
            unitSystem: UnitSystem::METRIC,
            translator: $this->getContainer()->get(TranslatorInterface::class),
        )->build();

        $this->assertEquals([], $dataset['rows']);
        $this->assertEquals(['primary' => null, 'secondary' => null], $dataset['defaults']);
    }
}
