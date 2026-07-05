<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

use App\Domain\Dashboard\Widget\ConfiguredWidgets;

final readonly class DashboardLayout implements \IteratorAggregate
{
    private function __construct(
        /** @var list<array{id: string, widget: string, width: int}> */
        private array $config,
    ) {
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->config);
    }

    /**
     * @return list<array{id: string, widget: string, width: int}>
     */
    public static function default(): array
    {
        return [
            ['id' => 'dashboardWidget-mostRecentActivities', 'widget' => 'mostRecentActivities', 'width' => 66, 'config' => ['numberOfActivitiesToDisplay' => 5]],
            ['id' => 'dashboardWidget-introText', 'widget' => 'introText', 'width' => 33],
            ['id' => 'dashboardWidget-weeklyStats', 'widget' => 'weeklyStats', 'width' => 100, 'config' => ['metricsDisplayOrder' => ['distance', 'movingTime', 'elevation']]],
            ['id' => 'dashboardWidget-activityGrid', 'widget' => 'activityGrid', 'width' => 100],
            ['id' => 'dashboardWidget-streaks', 'widget' => 'streaks', 'width' => 33, 'config' => ['subtitle' => null, 'sportTypesToInclude' => []]],
            ['id' => 'dashboardWidget-athleteProfile', 'widget' => 'athleteProfile', 'width' => 33],
            ['id' => 'dashboardWidget-eddington', 'widget' => 'eddington', 'width' => 33],
            ['id' => 'dashboardWidget-peakPowerOutputs', 'widget' => 'peakPowerOutputs', 'width' => 50],
            ['id' => 'dashboardWidget-heartRateZones', 'widget' => 'heartRateZones', 'width' => 50],
            ['id' => 'dashboardWidget-monthlyStats', 'widget' => 'monthlyStats', 'width' => 66, 'config' => [
                'enableLastXYearsByDefault' => 10, 'metricsDisplayOrder' => ['distance', 'movingTime', 'elevation'],
            ]],
            ['id' => 'dashboardWidget-mostRecentMilestones', 'widget' => 'mostRecentMilestones', 'width' => 33, 'config' => ['numberOfMilestonesToDisplay' => 5]],
            ['id' => 'dashboardWidget-trainingLoad', 'widget' => 'trainingLoad', 'width' => 100],
            ['id' => 'dashboardWidget-weekdayStats', 'widget' => 'weekdayStats', 'width' => 50],
            ['id' => 'dashboardWidget-dayTimeStats', 'widget' => 'dayTimeStats', 'width' => 50],
            ['id' => 'dashboardWidget-distanceBreakdown', 'widget' => 'distanceBreakdown', 'width' => 50],
            ['id' => 'dashboardWidget-gearStats', 'widget' => 'gearStats', 'width' => 50, 'config' => ['includeRetiredGear' => true]],
            ['id' => 'dashboardWidget-yearlyStats', 'widget' => 'yearlyStats', 'width' => 100, 'config' => ['enableLastXYearsByDefault' => 10, 'metricsDisplayOrder' => ['distance', 'movingTime', 'elevation']]],
            ['id' => 'dashboardWidget-zwiftStats', 'widget' => 'zwiftStats', 'width' => 50],
            ['id' => 'dashboardWidget-ftpHistory', 'widget' => 'ftpHistory', 'width' => 50],
            ['id' => 'dashboardWidget-challengeConsistency', 'widget' => 'challengeConsistency', 'width' => 50, 'config' => ['challenges' => []]],
            ['id' => 'dashboardWidget-mostRecentChallengesCompleted', 'widget' => 'mostRecentChallengesCompleted', 'width' => 50, 'config' => ['numberOfChallengesToDisplay' => 5]],
            ['id' => 'dashboardWidget-athleteWeightHistory', 'widget' => 'athleteWeightHistory', 'width' => 50],
        ];
    }

    /**
     * @param array<int, mixed> $config |null
     */
    public static function fromArray(
        ?array $config,
    ): self {
        if (null === $config || [] === $config) {
            $config = self::default();
        }

        foreach ($config as $widget) {
            foreach (['widget', 'width'] as $requiredKey) {
                if (array_key_exists($requiredKey, $widget)) {
                    continue;
                }
                throw new InvalidDashboardLayout(sprintf('"%s" property is required for each dashboard widget', $requiredKey));
            }

            if (!is_int($widget['width'])) {
                throw new InvalidDashboardLayout('"width" property must be a valid integer');
            }

            if (!in_array($widget['width'], ConfiguredWidgets::WIDTHS, true)) {
                throw new InvalidDashboardLayout(sprintf('"width" property must be one of [%s], found %s', implode(', ', ConfiguredWidgets::WIDTHS), $widget['width']));
            }

            if (array_key_exists('config', $widget)) {
                if (!is_array($widget['config'])) {
                    throw new InvalidDashboardLayout('"config" property must be an array');
                }
                foreach ($widget['config'] as $key => $value) {
                    if (is_null($value)) {
                        continue;
                    }
                    if (!is_int($value) && !is_string($value) && !is_float($value) && !is_bool($value) && !is_array($value)) {
                        throw new InvalidDashboardLayout(sprintf('Invalid type for config item "%s" in widget "%s". Expected int, string, float, bool or array.', $key, $widget['widget']));
                    }
                }
            }
        }

        return new self($config); // @phpstan-ignore argument.type
    }
}
