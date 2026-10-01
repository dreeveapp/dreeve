<?php

namespace App\Tests\Domain\Activity\Comparison;

use App\Domain\Activity\Comparison\ComparisonMetric;
use App\Domain\Activity\Comparison\ComparisonMetricDirection;
use App\Infrastructure\Measurement\UnitSystem;
use App\Tests\ContainerTestCase;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Contracts\Translation\TranslatorInterface;

class ComparisonMetricTest extends ContainerTestCase
{
    use MatchesSnapshots;

    public function testGetTranslations(): void
    {
        $snapshot = [];
        foreach (ComparisonMetric::cases() as $metric) {
            $snapshot[$metric->value] = $metric->trans($this->getContainer()->get(TranslatorInterface::class));
        }
        $this->assertMatchesJsonSnapshot($snapshot);
    }

    public function testGetUnitSymbol(): void
    {
        $snapshot = [];
        foreach (UnitSystem::cases() as $unitSystem) {
            foreach (ComparisonMetric::cases() as $metric) {
                $snapshot[$unitSystem->value][$metric->value] = $metric->getUnitSymbol($unitSystem);
            }
        }
        $this->assertMatchesJsonSnapshot($snapshot);
    }

    public function testGetChartValueFormatter(): void
    {
        $snapshot = [];
        foreach (ComparisonMetric::cases() as $metric) {
            $snapshot[$metric->value] = $metric->getChartValueFormatter();
        }
        $this->assertMatchesJsonSnapshot($snapshot);
    }

    public function testGetDirection(): void
    {
        $snapshot = [];
        foreach (ComparisonMetric::cases() as $metric) {
            $snapshot[$metric->value] = $metric->getDirection()->value;
        }
        $this->assertMatchesJsonSnapshot($snapshot);
    }

    public function testMetricsWithoutABetterDirection(): void
    {
        $neutral = array_values(array_filter(
            ComparisonMetric::cases(),
            fn (ComparisonMetric $metric): bool => ComparisonMetricDirection::NEUTRAL === $metric->getDirection()
        ));

        $this->assertEquals(
            [
                ComparisonMetric::AVERAGE_HEART_RATE,
                ComparisonMetric::CALORIES,
                ComparisonMetric::KILOJOULES,
                ComparisonMetric::ELEVATION,
            ],
            $neutral
        );
    }

    public function testOnlyDurationIsBetterWhenLower(): void
    {
        $lowerIsBetter = array_values(array_filter(
            ComparisonMetric::cases(),
            fn (ComparisonMetric $metric): bool => ComparisonMetricDirection::LOWER_IS_BETTER === $metric->getDirection()
        ));

        $this->assertEquals([ComparisonMetric::MOVING_TIME], $lowerIsBetter);
    }
}
